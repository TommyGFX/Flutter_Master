<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class BillingPaymentsService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createPaymentLink(string $tenantId, int $documentId, array $payload): array
    {
        $document = $this->findDocument($tenantId, $documentId);
        if ($document === null) {
            throw new RuntimeException('document_not_found');
        }

        $provider = strtolower(trim((string) ($payload['provider'] ?? 'stripe')));
        if (!in_array($provider, ['stripe', 'paypal'], true)) {
            throw new RuntimeException('invalid_provider');
        }

        $externalLinkId = trim((string) ($payload['payment_link_id'] ?? ''));
        if ($externalLinkId === '') {
            throw new RuntimeException('payment_link_id_required');
        }

        $url = trim((string) ($payload['url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('payment_url_required');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'open')));
        if (!in_array($status, ['open', 'paid', 'expired', 'cancelled'], true)) {
            throw new RuntimeException('invalid_payment_link_status');
        }

        $amount = $this->normalizeMoney($payload['amount'] ?? $document['grand_total']);

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_payment_links (tenant_id, document_id, provider, payment_link_id, payment_url, status, amount, currency_code, expires_at)
             VALUES (:tenant_id, :document_id, :provider, :payment_link_id, :payment_url, :status, :amount, :currency_code, :expires_at)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':document_id' => $documentId,
            ':provider' => $provider,
            ':payment_link_id' => $externalLinkId,
            ':payment_url' => $url,
            ':status' => $status,
            ':amount' => $amount,
            ':currency_code' => strtoupper((string) ($document['currency_code'] ?? 'EUR')),
            ':expires_at' => $this->nullableString($payload['expires_at'] ?? null),
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'provider' => $provider,
            'payment_link_id' => $externalLinkId,
            'status' => $status,
            'amount' => $amount,
        ];
    }

    public function listPaymentLinks(string $tenantId, int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, provider, payment_link_id, payment_url, status, amount, currency_code, expires_at, created_at, updated_at
             FROM billing_payment_links
             WHERE tenant_id = :tenant_id AND document_id = :document_id
             ORDER BY id DESC'
        );
        $stmt->execute([':tenant_id' => $tenantId, ':document_id' => $documentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordPayment(string $tenantId, int $documentId, array $payload): array
    {
        $document = $this->findDocument($tenantId, $documentId);
        if ($document === null) {
            throw new RuntimeException('document_not_found');
        }

        $amountPaid = $this->normalizeMoney($payload['amount_paid'] ?? null);
        if ($amountPaid <= 0) {
            throw new RuntimeException('amount_paid_must_be_positive');
        }

        $feeAmount = $this->normalizeMoney($payload['fee_amount'] ?? 0);
        $discountAmount = $this->normalizeMoney($payload['discount_amount'] ?? 0);

        $provider = strtolower(trim((string) ($payload['provider'] ?? 'manual')));
        $paymentStatus = strtolower(trim((string) ($payload['status'] ?? 'received')));
        if (!in_array($paymentStatus, ['received', 'pending', 'failed', 'refunded'], true)) {
            throw new RuntimeException('invalid_payment_status');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO billing_payments (tenant_id, document_id, provider, external_payment_id, status, amount_paid, fee_amount, discount_amount, notes, paid_at)
                 VALUES (:tenant_id, :document_id, :provider, :external_payment_id, :status, :amount_paid, :fee_amount, :discount_amount, :notes, :paid_at)'
            );
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':document_id' => $documentId,
                ':provider' => $provider,
                ':external_payment_id' => $this->nullableString($payload['external_payment_id'] ?? null),
                ':status' => $paymentStatus,
                ':amount_paid' => $amountPaid,
                ':fee_amount' => $feeAmount,
                ':discount_amount' => $discountAmount,
                ':notes' => $this->nullableString($payload['notes'] ?? null),
                ':paid_at' => $this->nullableString($payload['paid_at'] ?? null),
            ]);

            $totals = $this->paymentSummary($tenantId, $documentId, (float) $document['grand_total']);

            $newStatus = $totals['outstanding_amount'] <= 0.0 ? 'paid' : ((string) $document['status'] === 'paid' ? 'partially_paid' : (string) $document['status']);
            if ($newStatus === 'draft') {
                $newStatus = 'sent';
            }

            $updateDocument = $this->pdo->prepare('UPDATE billing_documents SET status = :status WHERE tenant_id = :tenant_id AND id = :document_id');
            $updateDocument->execute([
                ':status' => $newStatus,
                ':tenant_id' => $tenantId,
                ':document_id' => $documentId,
            ]);

            $this->pdo->commit();

            return [
                'document_id' => $documentId,
                'status' => $newStatus,
                'payment_totals' => $totals,
            ];
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    public function paymentSummary(string $tenantId, int $documentId, float $grandTotal): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN status = "received" THEN amount_paid ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN status = "received" THEN fee_amount ELSE 0 END), 0) AS fees,
                COALESCE(SUM(CASE WHEN status = "received" THEN discount_amount ELSE 0 END), 0) AS discounts
             FROM billing_payments
             WHERE tenant_id = :tenant_id AND document_id = :document_id'
        );
        $stmt->execute([':tenant_id' => $tenantId, ':document_id' => $documentId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['paid' => 0, 'fees' => 0, 'discounts' => 0];

        $effectivePaid = (float) $totals['paid'] + (float) $totals['discounts'] - (float) $totals['fees'];

        return [
            'grand_total' => round($grandTotal, 2),
            'amount_paid' => round((float) $totals['paid'], 2),
            'fee_amount' => round((float) $totals['fees'], 2),
            'discount_amount' => round((float) $totals['discounts'], 2),
            'effective_paid_amount' => round($effectivePaid, 2),
            'outstanding_amount' => round($grandTotal - $effectivePaid, 2),
        ];
    }

    public function listPayments(string $tenantId, int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, provider, external_payment_id, status, amount_paid, fee_amount, discount_amount, notes, paid_at, created_at
             FROM billing_payments
             WHERE tenant_id = :tenant_id AND document_id = :document_id
             ORDER BY COALESCE(paid_at, created_at) DESC, id DESC'
        );
        $stmt->execute([':tenant_id' => $tenantId, ':document_id' => $documentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveDunningConfig(string $tenantId, array $payload): array
    {
        $graceDays = max(0, (int) ($payload['grace_days'] ?? 3));
        $interestRate = $this->normalizeMoney($payload['interest_rate_percent'] ?? 5);
        $feeLevel1 = $this->normalizeMoney($payload['fee_level_1'] ?? 2.5);
        $feeLevel2 = $this->normalizeMoney($payload['fee_level_2'] ?? 5.0);
        $feeLevel3 = $this->normalizeMoney($payload['fee_level_3'] ?? 7.5);

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_dunning_configs (tenant_id, grace_days, interest_rate_percent, fee_level_1, fee_level_2, fee_level_3)
             VALUES (:tenant_id, :grace_days, :interest_rate_percent, :fee_level_1, :fee_level_2, :fee_level_3)
             ON DUPLICATE KEY UPDATE
                grace_days = VALUES(grace_days),
                interest_rate_percent = VALUES(interest_rate_percent),
                fee_level_1 = VALUES(fee_level_1),
                fee_level_2 = VALUES(fee_level_2),
                fee_level_3 = VALUES(fee_level_3)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':grace_days' => $graceDays,
            ':interest_rate_percent' => $interestRate,
            ':fee_level_1' => $feeLevel1,
            ':fee_level_2' => $feeLevel2,
            ':fee_level_3' => $feeLevel3,
        ]);

        return $this->getDunningConfig($tenantId);
    }

    public function getDunningConfig(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT grace_days, interest_rate_percent, fee_level_1, fee_level_2, fee_level_3, updated_at
             FROM billing_dunning_configs
             WHERE tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($config)) {
            return $config;
        }

        return [
            'grace_days' => 3,
            'interest_rate_percent' => 5.00,
            'fee_level_1' => 2.50,
            'fee_level_2' => 5.00,
            'fee_level_3' => 7.50,
            'updated_at' => null,
        ];
    }

    public function runDunning(string $tenantId): array
    {
        $config = $this->getDunningConfig($tenantId);
        $documents = $this->dueDocuments($tenantId, (int) $config['grace_days']);

        $created = 0;
        foreach ($documents as $document) {
            $summary = $this->paymentSummary($tenantId, (int) $document['id'], (float) $document['grand_total']);
            if ($summary['outstanding_amount'] <= 0) {
                continue;
            }

            $case = $this->findDunningCase($tenantId, (int) $document['id']);
            $nextLevel = min(3, ((int) ($case['current_level'] ?? 0)) + 1);
            $fee = (float) $config['fee_level_' . $nextLevel];
            $interest = round($summary['outstanding_amount'] * (((float) $config['interest_rate_percent']) / 100), 2);

            $this->upsertDunningCase($tenantId, (int) $document['id'], $nextLevel, $summary['outstanding_amount'], $fee, $interest);
            $this->insertDunningEvent($tenantId, (int) $document['id'], $nextLevel, $summary['outstanding_amount'], $fee, $interest);
            $created++;
        }

        return ['processed_documents' => count($documents), 'new_or_updated_cases' => $created];
    }

    public function listDunningCases(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.document_id, c.current_level, c.outstanding_amount, c.fee_amount, c.interest_amount, c.last_notice_at, c.updated_at,
                    d.document_number, d.customer_name_snapshot, d.due_date, d.status
             FROM billing_dunning_cases c
             INNER JOIN billing_documents d ON d.id = c.document_id AND d.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tenant_id
             ORDER BY c.updated_at DESC'
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveBankAccount(string $tenantId, array $payload): array
    {
        $iban = strtoupper(str_replace(' ', '', trim((string) ($payload['iban'] ?? ''))));
        if ($iban === '') {
            throw new RuntimeException('iban_required');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_bank_accounts (tenant_id, account_holder, iban, bic, bank_name, qr_iban_enabled)
             VALUES (:tenant_id, :account_holder, :iban, :bic, :bank_name, :qr_iban_enabled)
             ON DUPLICATE KEY UPDATE
                account_holder = VALUES(account_holder),
                iban = VALUES(iban),
                bic = VALUES(bic),
                bank_name = VALUES(bank_name),
                qr_iban_enabled = VALUES(qr_iban_enabled)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':account_holder' => $this->nullableString($payload['account_holder'] ?? null),
            ':iban' => $iban,
            ':bic' => strtoupper(trim((string) ($payload['bic'] ?? ''))),
            ':bank_name' => $this->nullableString($payload['bank_name'] ?? null),
            ':qr_iban_enabled' => !empty($payload['qr_iban_enabled']) ? 1 : 0,
        ]);

        return $this->getBankAccount($tenantId);
    }

    public function getBankAccount(string $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT account_holder, iban, bic, bank_name, qr_iban_enabled, updated_at
             FROM tenant_bank_accounts
             WHERE tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findDocument(string $tenantId, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, status, grand_total, currency_code, due_date FROM billing_documents WHERE tenant_id = :tenant_id AND id = :document_id LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':document_id' => $documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($document) ? $document : null;
    }

    private function dueDocuments(string $tenantId, int $graceDays): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, due_date, grand_total, status
             FROM billing_documents
             WHERE tenant_id = :tenant_id
               AND due_date IS NOT NULL
               AND due_date <= DATE_SUB(CURDATE(), INTERVAL :grace_days DAY)
               AND status IN ("sent", "due", "overdue", "partially_paid")'
        );
        $stmt->bindValue(':tenant_id', $tenantId);
        $stmt->bindValue(':grace_days', $graceDays, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findDunningCase(string $tenantId, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT current_level FROM billing_dunning_cases WHERE tenant_id = :tenant_id AND document_id = :document_id LIMIT 1');
        $stmt->execute([':tenant_id' => $tenantId, ':document_id' => $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function upsertDunningCase(string $tenantId, int $documentId, int $level, float $outstanding, float $fee, float $interest): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_dunning_cases (tenant_id, document_id, current_level, outstanding_amount, fee_amount, interest_amount, last_notice_at)
             VALUES (:tenant_id, :document_id, :current_level, :outstanding_amount, :fee_amount, :interest_amount, NOW())
             ON DUPLICATE KEY UPDATE
                current_level = VALUES(current_level),
                outstanding_amount = VALUES(outstanding_amount),
                fee_amount = VALUES(fee_amount),
                interest_amount = VALUES(interest_amount),
                last_notice_at = NOW()'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':document_id' => $documentId,
            ':current_level' => $level,
            ':outstanding_amount' => round($outstanding, 2),
            ':fee_amount' => round($fee, 2),
            ':interest_amount' => round($interest, 2),
        ]);
    }

    private function insertDunningEvent(string $tenantId, int $documentId, int $level, float $outstanding, float $fee, float $interest): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_dunning_events (tenant_id, document_id, dunning_level, outstanding_amount, fee_amount, interest_amount, sent_at)
             VALUES (:tenant_id, :document_id, :dunning_level, :outstanding_amount, :fee_amount, :interest_amount, NOW())'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':document_id' => $documentId,
            ':dunning_level' => $level,
            ':outstanding_amount' => round($outstanding, 2),
            ':fee_amount' => round($fee, 2),
            ':interest_amount' => round($interest, 2),
        ]);
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
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
