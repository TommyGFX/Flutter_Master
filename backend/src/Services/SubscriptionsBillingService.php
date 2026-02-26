<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\SubscriptionsBilling\PaymentMethodUpdateProviderRegistry;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SubscriptionsBillingService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?PaymentMethodUpdateProviderRegistry $paymentMethodProviderRegistry = null,
    ) {
    }

    public function listPlans(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, plan_key, name, billing_interval, amount, currency_code, term_months, auto_renew, notice_days, is_active, created_at, updated_at
             FROM subscription_plans
             WHERE tenant_id = :tenant_id
             ORDER BY is_active DESC, amount ASC, id DESC'
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function savePlan(string $tenantId, array $payload): array
    {
        $planKey = trim((string) ($payload['plan_key'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $interval = strtolower(trim((string) ($payload['billing_interval'] ?? 'monthly')));

        if ($planKey === '') {
            throw new RuntimeException('plan_key_required');
        }

        if ($name === '') {
            throw new RuntimeException('plan_name_required');
        }

        if (!in_array($interval, ['monthly', 'yearly'], true)) {
            throw new RuntimeException('invalid_billing_interval');
        }

        $amount = $this->normalizeMoney($payload['amount'] ?? null);
        if ($amount <= 0) {
            throw new RuntimeException('amount_must_be_positive');
        }

        $currencyCode = strtoupper(trim((string) ($payload['currency_code'] ?? 'EUR')));
        if (strlen($currencyCode) !== 3) {
            throw new RuntimeException('invalid_currency_code');
        }

        $termMonths = max(1, (int) ($payload['term_months'] ?? ($interval === 'yearly' ? 12 : 1)));
        $autoRenew = !array_key_exists('auto_renew', $payload) || (bool) $payload['auto_renew'];
        $noticeDays = max(0, (int) ($payload['notice_days'] ?? 30));
        $isActive = !array_key_exists('is_active', $payload) || (bool) $payload['is_active'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_plans (tenant_id, plan_key, name, billing_interval, amount, currency_code, term_months, auto_renew, notice_days, is_active)
             VALUES (:tenant_id, :plan_key, :name, :billing_interval, :amount, :currency_code, :term_months, :auto_renew, :notice_days, :is_active)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                billing_interval = VALUES(billing_interval),
                amount = VALUES(amount),
                currency_code = VALUES(currency_code),
                term_months = VALUES(term_months),
                auto_renew = VALUES(auto_renew),
                notice_days = VALUES(notice_days),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':plan_key' => $planKey,
            ':name' => $name,
            ':billing_interval' => $interval,
            ':amount' => $amount,
            ':currency_code' => $currencyCode,
            ':term_months' => $termMonths,
            ':auto_renew' => $autoRenew ? 1 : 0,
            ':notice_days' => $noticeDays,
            ':is_active' => $isActive ? 1 : 0,
        ]);

        $plan = $this->findPlanByKey($tenantId, $planKey);
        if ($plan === null) {
            throw new RuntimeException('plan_persist_failed');
        }

        return $plan;
    }

    public function listContracts(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.customer_id, c.plan_id, c.status, c.current_term_start, c.current_term_end, c.cancel_at, c.cancelled_at,
                    c.payment_method_ref, c.next_billing_at, c.created_at, c.updated_at,
                    p.plan_key, p.name AS plan_name, p.billing_interval, p.amount, p.currency_code
             FROM subscription_contracts c
             INNER JOIN subscription_plans p ON p.id = c.plan_id AND p.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tenant_id
             ORDER BY c.id DESC'
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createContract(string $tenantId, array $payload): array
    {
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $planId = (int) ($payload['plan_id'] ?? 0);
        if ($customerId <= 0 || $planId <= 0) {
            throw new RuntimeException('customer_id_and_plan_id_required');
        }

        $plan = $this->findPlanById($tenantId, $planId);
        if ($plan === null) {
            throw new RuntimeException('plan_not_found');
        }

        $this->assertCustomerExists($tenantId, $customerId);

        $startDate = $this->dateValue($payload['start_date'] ?? null, new DateTimeImmutable('today'));
        $termEnd = $this->calculateTermEnd($startDate, (int) $plan['term_months']);
        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        if (!in_array($status, ['trialing', 'active', 'paused', 'cancelled'], true)) {
            throw new RuntimeException('invalid_contract_status');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_contracts
                (tenant_id, customer_id, plan_id, status, current_term_start, current_term_end, payment_method_ref, next_billing_at)
             VALUES
                (:tenant_id, :customer_id, :plan_id, :status, :current_term_start, :current_term_end, :payment_method_ref, :next_billing_at)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':customer_id' => $customerId,
            ':plan_id' => $planId,
            ':status' => $status,
            ':current_term_start' => $startDate->format('Y-m-d'),
            ':current_term_end' => $termEnd->format('Y-m-d'),
            ':payment_method_ref' => $this->nullableString($payload['payment_method_ref'] ?? null),
            ':next_billing_at' => $startDate->format('Y-m-d H:i:s'),
        ]);

        return $this->getContract($tenantId, (int) $this->pdo->lastInsertId());
    }

    public function updateContract(string $tenantId, int $contractId, array $payload): array
    {
        $contract = $this->findContract($tenantId, $contractId);
        if ($contract === null) {
            throw new RuntimeException('contract_not_found');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? $contract['status'])));
        if (!in_array($status, ['trialing', 'active', 'paused', 'cancelled'], true)) {
            throw new RuntimeException('invalid_contract_status');
        }

        $cancelAt = $this->nullableDate($payload['cancel_at'] ?? ($contract['cancel_at'] ?? null));
        $paymentMethodRef = array_key_exists('payment_method_ref', $payload)
            ? $this->nullableString($payload['payment_method_ref'])
            : $this->nullableString($contract['payment_method_ref'] ?? null);

        $cancelledAt = $status === 'cancelled' && ($contract['cancelled_at'] ?? null) === null
            ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')
            : ($contract['cancelled_at'] ?? null);

        $stmt = $this->pdo->prepare(
            'UPDATE subscription_contracts
             SET status = :status,
                 cancel_at = :cancel_at,
                 cancelled_at = :cancelled_at,
                 payment_method_ref = :payment_method_ref,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id AND id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':cancel_at' => $cancelAt,
            ':cancelled_at' => $cancelledAt,
            ':payment_method_ref' => $paymentMethodRef,
            ':tenant_id' => $tenantId,
            ':id' => $contractId,
        ]);

        return $this->getContract($tenantId, $contractId);
    }

    public function changePlan(string $tenantId, int $contractId, array $payload): array
    {
        $contract = $this->findContract($tenantId, $contractId);
        if ($contract === null) {
            throw new RuntimeException('contract_not_found');
        }

        $newPlanId = (int) ($payload['plan_id'] ?? 0);
        if ($newPlanId <= 0) {
            throw new RuntimeException('plan_id_required');
        }

        $newPlan = $this->findPlanById($tenantId, $newPlanId);
        if ($newPlan === null) {
            throw new RuntimeException('plan_not_found');
        }

        $currentPlan = $this->findPlanById($tenantId, (int) $contract['plan_id']);
        if ($currentPlan === null) {
            throw new RuntimeException('current_plan_not_found');
        }

        $effectiveDate = $this->dateValue($payload['effective_date'] ?? null, new DateTimeImmutable('today'));
        $prorationCredit = $this->calculateProrationCredit($contract, $currentPlan, $effectiveDate);

        $termStart = $effectiveDate;
        $termEnd = $this->calculateTermEnd($termStart, (int) $newPlan['term_months']);

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(
                'UPDATE subscription_contracts
                 SET plan_id = :plan_id,
                     current_term_start = :term_start,
                     current_term_end = :term_end,
                     next_billing_at = :next_billing_at,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :id'
            );
            $update->execute([
                ':plan_id' => $newPlanId,
                ':term_start' => $termStart->format('Y-m-d'),
                ':term_end' => $termEnd->format('Y-m-d'),
                ':next_billing_at' => $termStart->format('Y-m-d H:i:s'),
                ':tenant_id' => $tenantId,
                ':id' => $contractId,
            ]);

            $this->insertCycle(
                $tenantId,
                $contractId,
                'plan_changed',
                $prorationCredit,
                $newPlan['currency_code'] ?? 'EUR',
                ['from_plan_id' => (int) $currentPlan['id'], 'to_plan_id' => $newPlanId, 'effective_date' => $termStart->format('Y-m-d')]
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'contract' => $this->getContract($tenantId, $contractId),
            'proration_credit' => $prorationCredit,
        ];
    }

    public function runRecurring(string $tenantId, ?string $asOf = null): array
    {
        $now = $this->dateTimeValue($asOf, new DateTimeImmutable('now'));
        $contracts = $this->dueContracts($tenantId, $now);
        $processed = 0;

        foreach ($contracts as $contract) {
            $plan = $this->findPlanById($tenantId, (int) $contract['plan_id']);
            if ($plan === null) {
                continue;
            }

            $amount = (float) $plan['amount'];
            $invoiceDocumentId = $this->createSubscriptionInvoice($tenantId, $contract, $plan, $now, $amount);
            $nextBilling = $this->nextBillingDate($now, (string) $plan['billing_interval']);

            $update = $this->pdo->prepare(
                'UPDATE subscription_contracts
                 SET next_billing_at = :next_billing_at,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :id'
            );
            $update->execute([
                ':next_billing_at' => $nextBilling->format('Y-m-d H:i:s'),
                ':tenant_id' => $tenantId,
                ':id' => (int) $contract['id'],
            ]);

            $this->insertCycle(
                $tenantId,
                (int) $contract['id'],
                'invoiced',
                $amount,
                (string) ($plan['currency_code'] ?? 'EUR'),
                ['billing_document_id' => $invoiceDocumentId, 'run_at' => $now->format(DATE_ATOM)]
            );

            $processed++;
        }

        return ['due_contracts' => count($contracts), 'processed' => $processed];
    }

    public function runAutoInvoicing(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT si.billing_document_id, d.customer_name_snapshot, d.document_number, d.grand_total, d.currency_code
             FROM subscription_invoices si
             INNER JOIN billing_documents d ON d.id = si.billing_document_id AND d.tenant_id = si.tenant_id
             WHERE si.tenant_id = :tenant_id AND si.delivery_status = :status
             ORDER BY si.id ASC'
        );
        $stmt->execute([':tenant_id' => $tenantId, ':status' => 'pending']);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $queueStmt = $this->pdo->prepare(
            'INSERT INTO email_queue (tenant_id, to_email, subject, body, status)
             VALUES (:tenant_id, :to_email, :subject, :body, :status)'
        );
        $updateStmt = $this->pdo->prepare(
            'UPDATE subscription_invoices
             SET delivery_status = :delivery_status,
                 delivered_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id AND billing_document_id = :billing_document_id'
        );

        $queued = 0;
        foreach ($rows as $row) {
            $queueStmt->execute([
                ':tenant_id' => $tenantId,
                ':to_email' => 'billing+' . strtolower($tenantId) . '@example.invalid',
                ':subject' => 'Abo-Rechnung ' . ($row['document_number'] ?? ('#' . $row['billing_document_id'])),
                ':body' => 'Ihre periodische Rechnung über ' . number_format((float) ($row['grand_total'] ?? 0), 2, ',', '.') . ' ' . ($row['currency_code'] ?? 'EUR') . ' wurde erstellt.',
                ':status' => 'queued',
            ]);

            $updateStmt->execute([
                ':delivery_status' => 'queued',
                ':tenant_id' => $tenantId,
                ':billing_document_id' => (int) $row['billing_document_id'],
            ]);

            $queued++;
        }

        return ['pending' => count($rows), 'queued' => $queued];
    }

    public function runDunningRetention(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT si.contract_id, si.billing_document_id, si.retry_attempts, d.grand_total,
                    COALESCE(SUM(CASE WHEN p.status IN ("received", "pending") THEN (p.amount_paid - p.fee_amount - p.discount_amount) ELSE 0 END), 0) AS settled
             FROM subscription_invoices si
             INNER JOIN billing_documents d ON d.id = si.billing_document_id AND d.tenant_id = si.tenant_id
             LEFT JOIN billing_payments p ON p.document_id = d.id AND p.tenant_id = d.tenant_id
             WHERE si.tenant_id = :tenant_id AND si.collection_status IN ("open", "retrying")
             GROUP BY si.contract_id, si.billing_document_id, si.retry_attempts, d.grand_total'
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $resolved = 0;
        $retried = 0;

        foreach ($rows as $row) {
            $outstanding = round((float) ($row['grand_total'] ?? 0) - (float) ($row['settled'] ?? 0), 2);
            $contractId = (int) $row['contract_id'];
            $documentId = (int) $row['billing_document_id'];

            if ($outstanding <= 0) {
                $this->updateCollectionState($tenantId, $contractId, $documentId, 'paid', (int) $row['retry_attempts']);
                $resolved++;
                continue;
            }

            $retryAttempts = (int) ($row['retry_attempts'] ?? 0) + 1;
            $status = $retryAttempts >= 3 ? 'failed' : 'retrying';
            $this->updateCollectionState($tenantId, $contractId, $documentId, $status, $retryAttempts);

            $upsert = $this->pdo->prepare(
                'INSERT INTO subscription_dunning_cases (tenant_id, contract_id, billing_document_id, retry_attempts, status, last_retry_at, payment_method_update_required)
                 VALUES (:tenant_id, :contract_id, :billing_document_id, :retry_attempts, :status, CURRENT_TIMESTAMP, :payment_method_update_required)
                 ON DUPLICATE KEY UPDATE
                    retry_attempts = VALUES(retry_attempts),
                    status = VALUES(status),
                    last_retry_at = VALUES(last_retry_at),
                    payment_method_update_required = VALUES(payment_method_update_required),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $upsert->execute([
                ':tenant_id' => $tenantId,
                ':contract_id' => $contractId,
                ':billing_document_id' => $documentId,
                ':retry_attempts' => $retryAttempts,
                ':status' => $status,
                ':payment_method_update_required' => $status === 'failed' ? 1 : 0,
            ]);

            $retried++;
        }

        return ['checked' => count($rows), 'resolved' => $resolved, 'retried' => $retried];
    }

    public function createPaymentMethodUpdateLink(string $tenantId, int $contractId, array $payload = []): array
    {
        $contract = $this->findContract($tenantId, $contractId);
        if ($contract === null) {
            throw new RuntimeException('contract_not_found');
        }

        $provider = strtolower(trim((string) ($payload['provider'] ?? 'stripe')));
        $token = bin2hex(random_bytes(16));

        $registry = $this->paymentMethodProviderRegistry ?? new PaymentMethodUpdateProviderRegistry([]);
        $adapter = $registry->resolve($provider);
        $resolvedLink = $adapter->createUpdateLink($tenantId, $contractId, $token, $contract, $payload);

        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_payment_method_updates (tenant_id, contract_id, provider, token, update_url, status)
             VALUES (:tenant_id, :contract_id, :provider, :token, :update_url, :status)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':contract_id' => $contractId,
            ':provider' => $provider,
            ':token' => $token,
            ':update_url' => $resolvedLink['update_url'],
            ':status' => $resolvedLink['status'],
        ]);

        return [
            'contract_id' => $contractId,
            'provider' => $provider,
            'token' => $token,
            'update_url' => $resolvedLink['update_url'],
            'status' => $resolvedLink['status'],
            'provider_response_json' => $resolvedLink['provider_response_json'],
        ];
    }

    public function completePaymentMethodUpdate(string $tenantId, array $payload): array
    {
        $token = strtolower(trim((string) ($payload['token'] ?? '')));
        $provider = strtolower(trim((string) ($payload['provider'] ?? 'stripe')));
        $status = strtolower(trim((string) ($payload['status'] ?? 'completed')));
        $paymentMethodRef = $this->nullableString($payload['payment_method_ref'] ?? null);

        if ($token === '') {
            throw new RuntimeException('token_required');
        }

        if (!in_array($status, ['completed', 'failed'], true)) {
            throw new RuntimeException('invalid_update_status');
        }

        return $this->persistPaymentMethodUpdate($tenantId, $provider, $token, $status, $paymentMethodRef, $payload);
    }

    public function handleProviderWebhook(string $provider, string $rawPayload, ?string $signatureHeader): array
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['stripe', 'paypal'], true)) {
            throw new RuntimeException('invalid_provider');
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('invalid_webhook_payload');
        }

        $this->assertWebhookSignature($provider, $rawPayload, $signatureHeader);

        return $provider === 'stripe'
            ? $this->handleStripeWebhookPayload($payload)
            : $this->handlePayPalWebhookPayload($payload);
    }

    private function handleStripeWebhookPayload(array $payload): array
    {
        $eventType = strtolower((string) ($payload['type'] ?? ''));
        $resource = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];
        $metadata = is_array($resource['metadata'] ?? null) ? $resource['metadata'] : [];

        $tenantId = trim((string) ($metadata['tenant_id'] ?? $metadata['tenant'] ?? ''));
        $token = strtolower(trim((string) ($metadata['token'] ?? '')));
        $paymentMethodRef = $this->nullableString($resource['payment_method'] ?? ($metadata['payment_method_ref'] ?? null));

        if ($tenantId === '' || $token === '') {
            throw new RuntimeException('webhook_context_missing');
        }

        $status = in_array($eventType, ['setup_intent.succeeded', 'checkout.session.completed', 'payment_method.attached'], true)
            ? 'completed'
            : 'failed';

        return $this->persistPaymentMethodUpdate($tenantId, 'stripe', $token, $status, $paymentMethodRef, $payload);
    }

    private function handlePayPalWebhookPayload(array $payload): array
    {
        $eventType = strtoupper((string) ($payload['event_type'] ?? ''));
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $custom = is_array($resource['custom_id'] ?? null)
            ? $resource['custom_id']
            : ['value' => $resource['custom_id'] ?? null];

        $tenantId = trim((string) ($resource['tenant_id'] ?? $payload['tenant_id'] ?? ''));
        $token = strtolower(trim((string) ($resource['token'] ?? ($custom['value'] ?? ''))));
        $paymentMethodRef = $this->nullableString($resource['payer_id'] ?? ($resource['id'] ?? null));

        if ($tenantId === '' || $token === '') {
            throw new RuntimeException('webhook_context_missing');
        }

        $status = str_contains($eventType, 'COMPLETED') || str_contains($eventType, 'APPROVED')
            ? 'completed'
            : 'failed';

        return $this->persistPaymentMethodUpdate($tenantId, 'paypal', $token, $status, $paymentMethodRef, $payload);
    }

    private function persistPaymentMethodUpdate(string $tenantId, string $provider, string $token, string $status, ?string $paymentMethodRef, array $payload): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, contract_id, status
             FROM subscription_payment_method_updates
             WHERE tenant_id = :tenant_id AND provider = :provider AND token = :token
             LIMIT 1'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':provider' => $provider,
            ':token' => $token,
        ]);
        $updateRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($updateRow)) {
            throw new RuntimeException('payment_method_update_not_found');
        }

        $contractId = (int) $updateRow['contract_id'];

        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare(
                'UPDATE subscription_payment_method_updates
                 SET status = :status,
                     completed_at = CASE WHEN :status = "completed" THEN CURRENT_TIMESTAMP ELSE completed_at END
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':status' => $status,
                ':id' => (int) $updateRow['id'],
            ]);

            if ($status === 'completed' && $paymentMethodRef !== null) {
                $contractStmt = $this->pdo->prepare(
                    'UPDATE subscription_contracts
                     SET payment_method_ref = :payment_method_ref,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE tenant_id = :tenant_id AND id = :id'
                );
                $contractStmt->execute([
                    ':payment_method_ref' => $paymentMethodRef,
                    ':tenant_id' => $tenantId,
                    ':id' => $contractId,
                ]);

                $dunningStmt = $this->pdo->prepare(
                    'UPDATE subscription_dunning_cases
                     SET payment_method_update_required = 0,
                         status = "retrying",
                         updated_at = CURRENT_TIMESTAMP
                     WHERE tenant_id = :tenant_id
                       AND contract_id = :contract_id
                       AND payment_method_update_required = 1'
                );
                $dunningStmt->execute([
                    ':tenant_id' => $tenantId,
                    ':contract_id' => $contractId,
                ]);
            }

            $this->insertCycle($tenantId, $contractId, 'payment_method_update_' . $status, 0.0, 'EUR', [
                'provider' => $provider,
                'token' => $token,
                'source' => $payload,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'tenant_id' => $tenantId,
            'contract_id' => $contractId,
            'provider' => $provider,
            'token' => $token,
            'status' => $status,
            'payment_method_ref' => $paymentMethodRef,
        ];
    }

    private function assertWebhookSignature(string $provider, string $rawPayload, ?string $signatureHeader): void
    {
        $secretEnv = $provider === 'stripe'
            ? 'SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET'
            : 'SUBSCRIPTIONS_PAYPAL_WEBHOOK_SECRET';
        $secret = getenv($secretEnv);
        if (!is_string($secret) || trim($secret) === '') {
            return;
        }

        if (!is_string($signatureHeader) || trim($signatureHeader) === '') {
            throw new InvalidArgumentException('webhook_signature_missing');
        }

        $computedSignature = hash_hmac('sha256', $rawPayload, trim($secret));
        if (!hash_equals($computedSignature, trim($signatureHeader))) {
            throw new InvalidArgumentException('webhook_signature_invalid');
        }
    }

    private function getContract(string $tenantId, int $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.customer_id, c.plan_id, c.status, c.current_term_start, c.current_term_end, c.cancel_at, c.cancelled_at,
                    c.payment_method_ref, c.next_billing_at, c.created_at, c.updated_at,
                    p.plan_key, p.name AS plan_name, p.billing_interval, p.amount, p.currency_code
             FROM subscription_contracts c
             INNER JOIN subscription_plans p ON p.id = c.plan_id AND p.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tenant_id AND c.id = :id
             LIMIT 1'
        );
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $contractId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('contract_not_found');
        }

        return $row;
    }

    private function findPlanById(string $tenantId, int $planId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, plan_key, name, billing_interval, amount, currency_code, term_months, auto_renew, notice_days, is_active FROM subscription_plans WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $planId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findPlanByKey(string $tenantId, string $planKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, plan_key, name, billing_interval, amount, currency_code, term_months, auto_renew, notice_days, is_active, created_at, updated_at FROM subscription_plans WHERE tenant_id = :tenant_id AND plan_key = :plan_key LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':plan_key' => $planKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findContract(string $tenantId, int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, customer_id, plan_id, status, current_term_start, current_term_end, cancel_at, cancelled_at, payment_method_ref, next_billing_at FROM subscription_contracts WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function dueContracts(string $tenantId, DateTimeImmutable $asOf): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, plan_id, status, current_term_start, current_term_end, next_billing_at
             FROM subscription_contracts
             WHERE tenant_id = :tenant_id
               AND status IN ("trialing", "active")
               AND next_billing_at <= :next_billing_at
             ORDER BY next_billing_at ASC'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':next_billing_at' => $asOf->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function createSubscriptionInvoice(string $tenantId, array $contract, array $plan, DateTimeImmutable $invoiceDate, float $amount): int
    {
        $totals = [
            'subtotal_net' => $amount,
            'discount_total' => 0.0,
            'shipping_total' => 0.0,
            'fees_total' => 0.0,
            'tax_total' => 0.0,
            'grand_total' => $amount,
            'tax_breakdown' => [],
        ];

        $documentStmt = $this->pdo->prepare(
            'INSERT INTO billing_documents
                (tenant_id, plugin_key, document_type, status, customer_id, customer_name_snapshot, currency_code, exchange_rate, subtotal_net, discount_total, shipping_total, fees_total, tax_total, grand_total, totals_json, due_date, finalized_at)
             VALUES
                (:tenant_id, :plugin_key, :document_type, :status, :customer_id, :customer_name_snapshot, :currency_code, :exchange_rate, :subtotal_net, :discount_total, :shipping_total, :fees_total, :tax_total, :grand_total, :totals_json, :due_date, :finalized_at)'
        );
        $documentStmt->execute([
            ':tenant_id' => $tenantId,
            ':plugin_key' => 'subscriptions_billing',
            ':document_type' => 'invoice',
            ':status' => 'sent',
            ':customer_id' => (int) $contract['customer_id'],
            ':customer_name_snapshot' => $this->customerDisplayName($tenantId, (int) $contract['customer_id']),
            ':currency_code' => (string) ($plan['currency_code'] ?? 'EUR'),
            ':exchange_rate' => 1.0,
            ':subtotal_net' => $amount,
            ':discount_total' => 0.0,
            ':shipping_total' => 0.0,
            ':fees_total' => 0.0,
            ':tax_total' => 0.0,
            ':grand_total' => $amount,
            ':totals_json' => json_encode($totals, JSON_THROW_ON_ERROR),
            ':due_date' => $invoiceDate->modify('+14 days')->format('Y-m-d'),
            ':finalized_at' => $invoiceDate->format('Y-m-d H:i:s'),
        ]);

        $documentId = (int) $this->pdo->lastInsertId();

        $lineStmt = $this->pdo->prepare(
            'INSERT INTO billing_line_items
                (tenant_id, document_id, position, name, description, quantity, unit_price, discount_percent, discount_amount, tax_rate, line_net)
             VALUES
                (:tenant_id, :document_id, :position, :name, :description, :quantity, :unit_price, :discount_percent, :discount_amount, :tax_rate, :line_net)'
        );
        $lineStmt->execute([
            ':tenant_id' => $tenantId,
            ':document_id' => $documentId,
            ':position' => 1,
            ':name' => (string) ($plan['name'] ?? 'Subscription Plan'),
            ':description' => 'Abo-Abrechnungsperiode',
            ':quantity' => 1,
            ':unit_price' => $amount,
            ':discount_percent' => 0,
            ':discount_amount' => 0,
            ':tax_rate' => 0,
            ':line_net' => $amount,
        ]);

        $invoiceStmt = $this->pdo->prepare(
            'INSERT INTO subscription_invoices
                (tenant_id, contract_id, billing_document_id, cycle_started_at, cycle_ended_at, billed_amount, currency_code, collection_status, delivery_status)
             VALUES
                (:tenant_id, :contract_id, :billing_document_id, :cycle_started_at, :cycle_ended_at, :billed_amount, :currency_code, :collection_status, :delivery_status)'
        );
        $invoiceStmt->execute([
            ':tenant_id' => $tenantId,
            ':contract_id' => (int) $contract['id'],
            ':billing_document_id' => $documentId,
            ':cycle_started_at' => $invoiceDate->format('Y-m-d'),
            ':cycle_ended_at' => $this->nextBillingDate($invoiceDate, (string) ($plan['billing_interval'] ?? 'monthly'))->modify('-1 day')->format('Y-m-d'),
            ':billed_amount' => $amount,
            ':currency_code' => (string) ($plan['currency_code'] ?? 'EUR'),
            ':collection_status' => 'open',
            ':delivery_status' => 'pending',
        ]);

        return $documentId;
    }

    private function updateCollectionState(string $tenantId, int $contractId, int $documentId, string $status, int $retryAttempts): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE subscription_invoices
             SET collection_status = :collection_status,
                 retry_attempts = :retry_attempts,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id AND contract_id = :contract_id AND billing_document_id = :billing_document_id'
        );
        $stmt->execute([
            ':collection_status' => $status,
            ':retry_attempts' => $retryAttempts,
            ':tenant_id' => $tenantId,
            ':contract_id' => $contractId,
            ':billing_document_id' => $documentId,
        ]);
    }

    private function insertCycle(string $tenantId, int $contractId, string $eventType, float $amount, string $currencyCode, array $metadata): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_cycles (tenant_id, contract_id, event_type, amount_delta, currency_code, metadata_json)
             VALUES (:tenant_id, :contract_id, :event_type, :amount_delta, :currency_code, :metadata_json)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':contract_id' => $contractId,
            ':event_type' => $eventType,
            ':amount_delta' => round($amount, 2),
            ':currency_code' => strtoupper($currencyCode),
            ':metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function customerDisplayName(string $tenantId, int $customerId): string
    {
        $stmt = $this->pdo->prepare('SELECT display_name FROM billing_customers WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $customerId]);
        $name = $stmt->fetchColumn();

        return is_string($name) && trim($name) !== '' ? trim($name) : ('Customer #' . $customerId);
    }

    private function assertCustomerExists(string $tenantId, int $customerId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM billing_customers WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $customerId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('customer_not_found');
        }
    }

    private function calculateProrationCredit(array $contract, array $currentPlan, DateTimeImmutable $effectiveDate): float
    {
        $termStart = $this->dateValue($contract['current_term_start'] ?? null, new DateTimeImmutable('today'));
        $termEnd = $this->dateValue($contract['current_term_end'] ?? null, $termStart->modify('+1 month'));
        $totalDays = max(1, (int) $termEnd->diff($termStart)->format('%a'));
        $remainingDays = max(0, (int) $termEnd->diff($effectiveDate)->format('%r%a'));

        if ($remainingDays <= 0) {
            return 0.0;
        }

        $fullAmount = (float) ($currentPlan['amount'] ?? 0);

        return round(($fullAmount / $totalDays) * $remainingDays, 2);
    }

    private function calculateTermEnd(DateTimeImmutable $startDate, int $termMonths): DateTimeImmutable
    {
        return $startDate->modify('+' . max(1, $termMonths) . ' months')->modify('-1 day');
    }

    private function nextBillingDate(DateTimeImmutable $baseDate, string $interval): DateTimeImmutable
    {
        if ($interval === 'yearly') {
            return $baseDate->modify('+1 year');
        }

        return $baseDate->modify('+1 month');
    }

    private function normalizeMoney(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function dateValue(mixed $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateTimeImmutable(trim($value));
            } catch (\Throwable) {
                throw new RuntimeException('invalid_date_format');
            }
        }

        return $fallback;
    }

    private function dateTimeValue(mixed $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        return $this->dateValue($value, $fallback);
    }

    private function nullableDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->dateValue($value, new DateTimeImmutable('today'))->format('Y-m-d');
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
