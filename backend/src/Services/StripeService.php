<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use InvalidArgumentException;
use Stripe\Checkout\Session;
use Stripe\CustomerPortal\Session as CustomerPortalSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey($this->getRequiredEnv('STRIPE_SECRET_KEY'));
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

        $returnUrl = $this->resolveUrl($payload['return_url'] ?? null, 'STRIPE_PORTAL_RETURN_URL');

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
        $eventType = (string) ($event['type'] ?? '');

        match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionLifecycle($event),
            default => null,
        };
    }

    private function handleCheckoutCompleted(array $event): void
    {
        // Platzhalter für Persistenz/Domain-Events (z. B. Provisionierung Tenant, Rechnungslogik).
    }

    private function handleSubscriptionLifecycle(array $event): void
    {
        // Platzhalter für Persistenz/Domain-Events (Abo-Statusänderungen, Kündigungen, Dunning).
    }

    private function resolveUrl(mixed $value, string $envKey): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $this->getRequiredEnv($envKey);
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
