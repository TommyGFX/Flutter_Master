<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use InvalidArgumentException;
use PDO;
use Stripe\Checkout\Session;
use Stripe\CustomerPortal\Session as CustomerPortalSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripeService
{
    private PDO $pdo;

    public function __construct()
    {
        Stripe::setApiKey($this->getRequiredEnv('STRIPE_SECRET_KEY'));
        $this->pdo = Database::connection();
    }

    public function createCheckoutSession(array $payload): array
    {
        $lineItems = $payload['line_items'] ?? null;
        if (!is_array($lineItems) || $lineItems === []) {
            throw new InvalidArgumentException('line_items muss als nicht-leeres Array übergeben werden.');
        }

        $mode = (string) ($payload['mode'] ?? 'subscription');
        if (!in_array($mode, ['payment', 'setup', 'subscription'], true)) {
            throw new InvalidArgumentException('mode muss payment, setup oder subscription sein.');
        }

        $params = [
            'mode' => $mode,
            'line_items' => $lineItems,
            'success_url' => $this->resolveUrl($payload['success_url'] ?? null, 'STRIPE_CHECKOUT_SUCCESS_URL'),
            'cancel_url' => $this->resolveUrl($payload['cancel_url'] ?? null, 'STRIPE_CHECKOUT_CANCEL_URL'),
            'client_reference_id' => $payload['client_reference_id'] ?? null,
            'customer' => $payload['customer_id'] ?? null,
            'customer_email' => $payload['customer_email'] ?? null,
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            'allow_promotion_codes' => (bool) ($payload['allow_promotion_codes'] ?? true),
        ];

        if (is_array($payload['subscription_data'] ?? null) && $mode === 'subscription') {
            $params['subscription_data'] = $payload['subscription_data'];
        }

        $session = Session::create(array_filter($params, static fn (mixed $value): bool => $value !== null));

        return [
            'id' => $session->id,
            'url' => $session->url,
            'status' => $session->status,
            'mode' => $session->mode,
        ];
    }

    public function createCustomerPortalSession(array $payload): array
    {
        $customerId = $payload['customer_id'] ?? null;
        if (!is_string($customerId) || $customerId === '') {
            throw new InvalidArgumentException('customer_id ist erforderlich.');
        }

        $returnUrl = $this->resolveCustomerPortalReturnUrl($payload['return_url'] ?? null);

        $session = CustomerPortalSession::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return [
            'id' => $session->id,
            'url' => $session->url,
        ];
    }

    public function parseEvent(string $payload, ?string $signature): array
    {
        $webhookSecret = Env::get('STRIPE_WEBHOOK_SECRET');
        if ($webhookSecret !== null && $webhookSecret !== '') {
            if ($signature === null || $signature === '') {
                throw new InvalidArgumentException('Stripe-Signatur fehlt.');
            }

            try {
                $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
            } catch (UnexpectedValueException|SignatureVerificationException $exception) {
                throw new InvalidArgumentException('Ungültiger Stripe-Webhook: ' . $exception->getMessage());
            }

            return $event->toArray();
        }

        return json_decode($payload, true) ?: [];
    }

    public function handleWebhook(array $event): void
    {
        $eventType = (string) ($event['type'] ?? 'unknown');
        $eventId = (string) ($event['id'] ?? '');
        $resource = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
        $tenantId = $this->extractTenantId($resource, $event);

        $eventPk = $this->storeWebhookEvent($event, $eventType, $eventId, $tenantId, $resource);
        if ($eventPk === null) {
            return;
        }

        $status = 'processed';
        $errorMessage = null;

        try {
            match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($eventPk, $event, $tenantId, $resource),
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionLifecycle($eventPk, $event, $tenantId, $resource),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($eventPk, $tenantId, $resource),
            'invoice.paid' => $this->handleInvoicePaid($eventPk, $tenantId, $resource),
            default => null,
        };
        } catch (\Throwable $exception) {
            $status = 'failed';
            $errorMessage = $exception->getMessage();
            throw $exception;
        } finally {
            $this->markWebhookProcessed($eventPk, $status, $errorMessage);
        }
    }

    private function handleCheckoutCompleted(int $eventPk, array $event, string $tenantId, array $resource): void
    {
        $sessionId = (string) ($resource['id'] ?? '');
        $customerId = (string) ($resource['customer'] ?? '');
        $status = (string) ($resource['status'] ?? 'completed');

        if ($tenantId === '' || $sessionId === '') {
            return;
        }

        $statement = $this->pdo->prepare('INSERT INTO tenant_provisioning_events (tenant_id, stripe_event_pk, stripe_session_id, stripe_customer_id, provisioning_status, payload_json)
            VALUES (:tenant_id, :stripe_event_pk, :stripe_session_id, :stripe_customer_id, :provisioning_status, :payload_json)
            ON DUPLICATE KEY UPDATE
                stripe_customer_id = VALUES(stripe_customer_id),
                provisioning_status = VALUES(provisioning_status),
                payload_json = VALUES(payload_json),
                updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'tenant_id' => $tenantId,
            'stripe_event_pk' => $eventPk,
            'stripe_session_id' => $sessionId,
            'stripe_customer_id' => $customerId !== '' ? $customerId : null,
            'provisioning_status' => $status,
            'payload_json' => json_encode($event, JSON_THROW_ON_ERROR),
        ]);
    }

    private function handleSubscriptionLifecycle(int $eventPk, array $event, string $tenantId, array $resource): void
    {
        $subscriptionId = (string) ($resource['id'] ?? '');
        $status = (string) ($resource['status'] ?? 'active');

        if ($tenantId === '' || $subscriptionId === '') {
            return;
        }

        $statement = $this->pdo->prepare('INSERT INTO tenant_subscription_entitlements
            (tenant_id, stripe_event_pk, stripe_subscription_id, entitlement_status, current_period_end, payload_json)
            VALUES (:tenant_id, :stripe_event_pk, :stripe_subscription_id, :entitlement_status, :current_period_end, :payload_json)
            ON DUPLICATE KEY UPDATE
                stripe_event_pk = VALUES(stripe_event_pk),
                entitlement_status = VALUES(entitlement_status),
                current_period_end = VALUES(current_period_end),
                payload_json = VALUES(payload_json),
                updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'tenant_id' => $tenantId,
            'stripe_event_pk' => $eventPk,
            'stripe_subscription_id' => $subscriptionId,
            'entitlement_status' => $status,
            'current_period_end' => $this->resolveStripeTimestamp($resource['current_period_end'] ?? null),
            'payload_json' => json_encode($event, JSON_THROW_ON_ERROR),
        ]);
    }

    private function handleInvoicePaymentFailed(int $eventPk, string $tenantId, array $resource): void
    {
        $invoiceId = (string) ($resource['id'] ?? '');
        if ($tenantId === '' || $invoiceId === '') {
            return;
        }

        $attemptCount = (int) ($resource['attempt_count'] ?? 0);

        $statement = $this->pdo->prepare('INSERT INTO stripe_dunning_cases
            (tenant_id, stripe_event_pk, stripe_invoice_id, dunning_status, attempt_count, next_payment_attempt_at, payload_json)
            VALUES (:tenant_id, :stripe_event_pk, :stripe_invoice_id, :dunning_status, :attempt_count, :next_payment_attempt_at, :payload_json)
            ON DUPLICATE KEY UPDATE
                stripe_event_pk = VALUES(stripe_event_pk),
                dunning_status = VALUES(dunning_status),
                attempt_count = VALUES(attempt_count),
                next_payment_attempt_at = VALUES(next_payment_attempt_at),
                payload_json = VALUES(payload_json),
                updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'tenant_id' => $tenantId,
            'stripe_event_pk' => $eventPk,
            'stripe_invoice_id' => $invoiceId,
            'dunning_status' => 'open',
            'attempt_count' => $attemptCount,
            'next_payment_attempt_at' => $this->resolveStripeTimestamp($resource['next_payment_attempt'] ?? null),
            'payload_json' => json_encode($resource, JSON_THROW_ON_ERROR),
        ]);
    }

    private function handleInvoicePaid(int $eventPk, string $tenantId, array $resource): void
    {
        $invoiceId = (string) ($resource['id'] ?? '');
        if ($tenantId === '' || $invoiceId === '') {
            return;
        }

        $statement = $this->pdo->prepare('UPDATE stripe_dunning_cases
            SET stripe_event_pk = :stripe_event_pk,
                dunning_status = :dunning_status,
                resolved_at = CURRENT_TIMESTAMP,
                payload_json = :payload_json,
                updated_at = CURRENT_TIMESTAMP
            WHERE tenant_id = :tenant_id
              AND stripe_invoice_id = :stripe_invoice_id');
        $statement->execute([
            'stripe_event_pk' => $eventPk,
            'dunning_status' => 'resolved',
            'payload_json' => json_encode($resource, JSON_THROW_ON_ERROR),
            'tenant_id' => $tenantId,
            'stripe_invoice_id' => $invoiceId,
        ]);
    }

    private function storeWebhookEvent(array $event, string $eventType, string $eventId, string $tenantId, array $resource): ?int
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('Stripe-Event enthält keine ID.');
        }

        $statement = $this->pdo->prepare('INSERT INTO stripe_webhook_events
            (stripe_event_id, event_type, tenant_id, stripe_customer_id, stripe_subscription_id, event_status, payload_json)
            VALUES (:stripe_event_id, :event_type, :tenant_id, :stripe_customer_id, :stripe_subscription_id, :event_status, :payload_json)');

        try {
            $statement->execute([
                'stripe_event_id' => $eventId,
                'event_type' => $eventType,
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'stripe_customer_id' => $this->stringOrNull($resource['customer'] ?? null),
                'stripe_subscription_id' => $this->stringOrNull($resource['subscription'] ?? ($resource['id'] ?? null)),
                'event_status' => 'received',
                'payload_json' => json_encode($event, JSON_THROW_ON_ERROR),
            ]);
        } catch (\PDOException $exception) {
            if ((int) $exception->getCode() === 23000) {
                return null;
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function markWebhookProcessed(int $eventPk, string $status, ?string $errorMessage): void
    {
        $statement = $this->pdo->prepare('UPDATE stripe_webhook_events
            SET event_status = :event_status,
                error_message = :error_message,
                processed_at = CURRENT_TIMESTAMP
            WHERE id = :id');
        $statement->execute([
            'event_status' => $status,
            'error_message' => $errorMessage,
            'id' => $eventPk,
        ]);
    }

    private function extractTenantId(array $resource, array $event): string
    {
        $metadata = $resource['metadata'] ?? [];
        if (is_array($metadata)) {
            foreach (['tenant_id', 'tenant'] as $key) {
                $value = $metadata[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        $referenceId = $resource['client_reference_id'] ?? $event['client_reference_id'] ?? null;
        return is_string($referenceId) ? $referenceId : '';
    }

    private function resolveStripeTimestamp(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', (int) $value);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function resolveUrl(mixed $value, string $envKey): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $this->getRequiredEnv($envKey);
    }

    private function resolveCustomerPortalReturnUrl(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $portalReturnUrl = Env::get('STRIPE_PORTAL_RETURN_URL');
        if (is_string($portalReturnUrl) && $portalReturnUrl !== '') {
            return $portalReturnUrl;
        }

        $checkoutSuccessUrl = Env::get('STRIPE_CHECKOUT_SUCCESS_URL');
        if (is_string($checkoutSuccessUrl) && $checkoutSuccessUrl !== '') {
            return $checkoutSuccessUrl;
        }

        throw new InvalidArgumentException('Fehlende Umgebungsvariable: STRIPE_PORTAL_RETURN_URL (alternativ STRIPE_CHECKOUT_SUCCESS_URL).');
    }

    private function getRequiredEnv(string $key): string
    {
        $value = Env::get($key);
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Fehlende Umgebungsvariable: {$key}");
        }

        return $value;
    }
}
