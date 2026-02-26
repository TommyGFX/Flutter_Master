<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class FinanceReportingService
{
    private const CONNECTOR_PROVIDERS = ['lexoffice', 'sevdesk', 'fastbill'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function kpiDashboard(string $tenantId, ?string $fromDate, ?string $toDate): array
    {
        [$from, $to] = $this->resolveDateRange($fromDate, $toDate, '-30 days');

        $revenueStmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE
                        WHEN document_type IN ('invoice', 'order_confirmation') THEN grand_total
                        WHEN document_type IN ('credit_note', 'cancellation') THEN -grand_total
                        ELSE 0
                    END), 0) AS revenue
             FROM billing_documents
             WHERE tenant_id = :tenant_id
               AND finalized_at IS NOT NULL
               AND DATE(finalized_at) BETWEEN :from_date AND :to_date"
        );
        $revenueStmt->execute(['tenant_id' => $tenantId, 'from_date' => $from, 'to_date' => $to]);
        $revenue = (float) (($revenueStmt->fetch()['revenue'] ?? 0));

        $openReceivables = $this->openReceivablesTotal($tenantId, $to);

        $mrrArr = $this->mrrArr($tenantId);
        $days = max(1, (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1);
        $dailyRevenue = $revenue > 0 ? $revenue / $days : 0.0;
        $dso = $dailyRevenue > 0 ? round($openReceivables / $dailyRevenue, 2) : null;

        return [
            'range' => ['from' => $from, 'to' => $to],
            'revenue' => round($revenue, 2),
            'open_receivables' => round($openReceivables, 2),
            'mrr' => $mrrArr['mrr'],
            'arr' => $mrrArr['arr'],
            'dso' => $dso,
        ];
    }

    public function openItems(string $tenantId, ?string $asOf): array
    {
        $asOfDate = $this->normalizeDate($asOf) ?? date('Y-m-d');

        $stmt = $this->pdo->prepare(
            "SELECT d.id, d.document_number, d.document_type, d.status, d.due_date, d.finalized_at, d.grand_total,
                    COALESCE(NULLIF(c.company_name, ''), TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')))) AS customer_name,
                    COALESCE(dc.current_level, 0) AS dunning_level,
                    COALESCE(SUM(CASE WHEN p.status = 'received' THEN p.amount ELSE 0 END), 0) AS paid_amount
             FROM billing_documents d
             LEFT JOIN billing_customers c ON c.id = d.customer_id
             LEFT JOIN billing_dunning_cases dc ON dc.tenant_id = d.tenant_id AND dc.document_id = d.id
             LEFT JOIN billing_payments p ON p.tenant_id = d.tenant_id AND p.document_id = d.id
             WHERE d.tenant_id = :tenant_id
               AND d.document_type IN ('invoice', 'order_confirmation')
               AND d.finalized_at IS NOT NULL
             GROUP BY d.id, d.document_number, d.document_type, d.status, d.due_date, d.finalized_at, d.grand_total, customer_name, dunning_level
             HAVING paid_amount < d.grand_total
             ORDER BY COALESCE(d.due_date, DATE(d.finalized_at)) ASC, d.id ASC"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $items = [];
        $summary = [
            'total_open_amount' => 0.0,
            'bucket_current' => 0.0,
            'bucket_1_30' => 0.0,
            'bucket_31_60' => 0.0,
            'bucket_61_90' => 0.0,
            'bucket_90_plus' => 0.0,
        ];

        foreach ($stmt->fetchAll() ?: [] as $row) {
            $outstanding = max(0.0, (float) ($row['grand_total'] ?? 0) - (float) ($row['paid_amount'] ?? 0));
            $dueDate = is_string($row['due_date'] ?? null) ? (string) $row['due_date'] : null;
            $agingDays = $dueDate === null ? 0 : (int) floor((strtotime($asOfDate) - strtotime($dueDate)) / 86400);
            $agingDays = max(0, $agingDays);

            $summary['total_open_amount'] += $outstanding;
            $this->increaseAgingBucket($summary, $agingDays, $outstanding);

            $items[] = [
                'document_id' => (int) ($row['id'] ?? 0),
                'document_number' => $row['document_number'] ?? null,
                'document_type' => (string) ($row['document_type'] ?? 'invoice'),
                'status' => (string) ($row['status'] ?? 'due'),
                'customer_name' => trim((string) ($row['customer_name'] ?? '')),
                'due_date' => $dueDate,
                'outstanding_amount' => round($outstanding, 2),
                'aging_days' => $agingDays,
                'dunning_level' => (int) ($row['dunning_level'] ?? 0),
            ];
        }

        return [
            'as_of' => $asOfDate,
            'summary' => array_map(static fn (float $value): float => round($value, 2), $summary),
            'items' => $items,
        ];
    }

    public function taxReport(string $tenantId, ?string $fromDate, ?string $toDate): array
    {
        [$from, $to] = $this->resolveDateRange($fromDate, $toDate, 'first day of this month');

        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(COALESCE(d.finalized_at, d.created_at), '%Y-%m') AS period,
                    ROUND(tb.tax_rate, 2) AS tax_rate,
                    SUM(tb.taxable_net) AS net_total,
                    SUM(tb.tax_amount) AS tax_total,
                    SUM(tb.gross_amount) AS gross_total
             FROM billing_tax_breakdowns tb
             INNER JOIN billing_documents d ON d.id = tb.document_id AND d.tenant_id = tb.tenant_id
             WHERE d.tenant_id = :tenant_id
               AND d.finalized_at IS NOT NULL
               AND DATE(d.finalized_at) BETWEEN :from_date AND :to_date
             GROUP BY period, ROUND(tb.tax_rate, 2)
             ORDER BY period ASC, tax_rate ASC"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'from_date' => $from, 'to_date' => $to]);

        $lines = [];
        $totals = ['net_total' => 0.0, 'tax_total' => 0.0, 'gross_total' => 0.0];

        foreach ($stmt->fetchAll() ?: [] as $row) {
            $net = (float) ($row['net_total'] ?? 0);
            $tax = (float) ($row['tax_total'] ?? 0);
            $gross = (float) ($row['gross_total'] ?? 0);
            $totals['net_total'] += $net;
            $totals['tax_total'] += $tax;
            $totals['gross_total'] += $gross;

            $lines[] = [
                'period' => (string) ($row['period'] ?? ''),
                'tax_rate' => (float) ($row['tax_rate'] ?? 0),
                'net_total' => round($net, 2),
                'tax_total' => round($tax, 2),
                'gross_total' => round($gross, 2),
            ];
        }

        return [
            'range' => ['from' => $from, 'to' => $to],
            'ust_va_summary' => array_map(static fn (float $value): float => round($value, 2), $totals),
            'oss_optional_supported' => true,
            'lines' => $lines,
        ];
    }

    public function export(string $tenantId, string $type, string $format, ?string $fromDate, ?string $toDate): array
    {
        $normalizedType = strtolower(trim($type));
        $normalizedFormat = strtolower(trim($format));

        if (!in_array($normalizedType, ['datev', 'op', 'tax'], true)) {
            throw new RuntimeException('invalid_export_type');
        }

        if (!in_array($normalizedFormat, ['csv', 'excel'], true)) {
            throw new RuntimeException('invalid_export_format');
        }

        $payload = match ($normalizedType) {
            'datev' => $this->buildDatevRows($tenantId, $fromDate, $toDate),
            'op' => $this->openItems($tenantId, $toDate),
            'tax' => $this->taxReport($tenantId, $fromDate, $toDate),
            default => throw new RuntimeException('invalid_export_type'),
        };

        return [
            'type' => $normalizedType,
            'format' => $normalizedFormat,
            'generated_at' => gmdate(DATE_ATOM),
            'filename' => $this->exportFilename($normalizedType, $normalizedFormat),
            'payload' => $payload,
        ];
    }

    public function buildExportStream(string $tenantId, string $type, string $format, ?string $fromDate, ?string $toDate): array
    {
        $export = $this->export($tenantId, $type, $format, $fromDate, $toDate);
        $normalizedType = (string) ($export['type'] ?? 'datev');
        $normalizedFormat = (string) ($export['format'] ?? 'csv');
        $payload = is_array($export['payload'] ?? null) ? $export['payload'] : [];
        $rows = $this->flattenExportRows($normalizedType, $payload);

        return [
            'filename' => $this->exportFilename($normalizedType, $normalizedFormat),
            'content_type' => $this->exportContentType($normalizedFormat),
            'stream_writer' => function () use ($rows, $normalizedFormat): void {
                $this->writeTabularStream($rows, $normalizedFormat);
            },
        ];
    }

    public function listConnectors(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT provider, webhook_url, is_enabled, credentials_json, updated_at
             FROM finance_reporting_connectors
             WHERE tenant_id = :tenant_id
             ORDER BY provider ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $credentials = [];
            $decoded = json_decode((string) ($row['credentials_json'] ?? '[]'), true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($key) && trim($key) !== '') {
                        $credentials[$key] = $value === null || $value === '' ? null : '***';
                    }
                }
            }

            return [
                'provider' => (string) ($row['provider'] ?? ''),
                'webhook_url' => $row['webhook_url'] ?? null,
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                'credentials' => $credentials,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $stmt->fetchAll() ?: []);
    }

    public function upsertConnector(string $tenantId, array $payload): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        if (!in_array($provider, self::CONNECTOR_PROVIDERS, true)) {
            throw new RuntimeException('invalid_connector_provider');
        }

        $webhookUrl = $this->nullableString($payload['webhook_url'] ?? null);
        $isEnabled = (bool) ($payload['is_enabled'] ?? false);
        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];

        $stmt = $this->pdo->prepare(
            'INSERT INTO finance_reporting_connectors (tenant_id, provider, webhook_url, credentials_json, is_enabled)
             VALUES (:tenant_id, :provider, :webhook_url, :credentials_json, :is_enabled)
             ON DUPLICATE KEY UPDATE
                webhook_url = VALUES(webhook_url),
                credentials_json = VALUES(credentials_json),
                is_enabled = VALUES(is_enabled),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'webhook_url' => $webhookUrl,
            'credentials_json' => json_encode($credentials, JSON_THROW_ON_ERROR),
            'is_enabled' => $isEnabled ? 1 : 0,
        ]);

        $all = $this->listConnectors($tenantId);
        foreach ($all as $connector) {
            if (($connector['provider'] ?? '') === $provider) {
                return $connector;
            }
        }

        throw new RuntimeException('connector_persist_failed');
    }

    public function publishWebhook(string $tenantId, string $provider, array $payload): array
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, self::CONNECTOR_PROVIDERS, true)) {
            throw new RuntimeException('invalid_connector_provider');
        }

        $connector = $this->connectorConfig($tenantId, $provider);
        if ($connector === null || !(bool) ($connector['is_enabled'] ?? false)) {
            throw new RuntimeException('connector_not_enabled');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO finance_reporting_webhook_logs (tenant_id, provider, webhook_url, payload_json, delivery_status)
             VALUES (:tenant_id, :provider, :webhook_url, :payload_json, :delivery_status)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'webhook_url' => $connector['webhook_url'] ?? null,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'delivery_status' => 'queued',
        ]);

        return [
            'provider' => $provider,
            'status' => 'queued',
            'webhook_url' => $connector['webhook_url'] ?? null,
        ];
    }

    public function syncConnectors(string $tenantId, int $limit = 25): array
    {
        $effectiveLimit = min(100, max(1, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT id, provider, webhook_url, payload_json
             FROM finance_reporting_webhook_logs
             WHERE tenant_id = :tenant_id
               AND delivery_status = :delivery_status
             ORDER BY created_at ASC, id ASC
             LIMIT ' . $effectiveLimit
        );
        $stmt->execute(['tenant_id' => $tenantId, 'delivery_status' => 'queued']);
        $rows = $stmt->fetchAll() ?: [];

        $processed = 0;
        $delivered = 0;
        $failed = 0;
        $results = [];

        foreach ($rows as $row) {
            $processed++;
            $id = (int) ($row['id'] ?? 0);
            $provider = (string) ($row['provider'] ?? '');
            $webhookUrl = $this->nullableString($row['webhook_url'] ?? null);
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);

            if ($webhookUrl === null) {
                $this->updateWebhookLogStatus($tenantId, $id, 'failed');
                $failed++;
                $results[] = ['id' => $id, 'provider' => $provider, 'status' => 'failed', 'reason' => 'missing_webhook_url'];
                continue;
            }

            $delivery = $this->deliverWebhook($tenantId, $provider, $webhookUrl, is_array($payload) ? $payload : []);
            $status = ($delivery['delivered'] ?? false) === true ? 'delivered' : 'failed';
            $this->updateWebhookLogStatus($tenantId, $id, $status);
            if ($status === 'delivered') {
                $delivered++;
            } else {
                $failed++;
            }

            $results[] = [
                'id' => $id,
                'provider' => $provider,
                'status' => $status,
                'http_status' => $delivery['http_status'] ?? null,
            ];
        }

        return [
            'processed' => $processed,
            'delivered' => $delivered,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    private function connectorConfig(string $tenantId, string $provider): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT provider, webhook_url, is_enabled
             FROM finance_reporting_connectors
             WHERE tenant_id = :tenant_id AND provider = :provider
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'provider' => $provider]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'provider' => (string) ($row['provider'] ?? ''),
            'webhook_url' => $row['webhook_url'] ?? null,
            'is_enabled' => (bool) ($row['is_enabled'] ?? false),
        ];
    }

    private function updateWebhookLogStatus(string $tenantId, int $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE finance_reporting_webhook_logs
             SET delivery_status = :delivery_status
             WHERE tenant_id = :tenant_id AND id = :id'
        );
        $stmt->execute([
            'delivery_status' => $status,
            'tenant_id' => $tenantId,
            'id' => $id,
        ]);
    }

    private function deliverWebhook(string $tenantId, string $provider, string $webhookUrl, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = (string) (getenv('FINANCE_REPORTING_CONNECTOR_WEBHOOK_SECRET') ?: '');
        $signature = hash_hmac('sha256', $body, $secret !== '' ? $secret : $tenantId . ':' . $provider);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-Finance-Tenant: ' . $tenantId,
                    'X-Finance-Provider: ' . $provider,
                    'X-Finance-Signature: ' . $signature,
                ]),
                'content' => $body,
            ],
        ]);

        $result = @file_get_contents($webhookUrl, false, $context);
        $httpStatus = $this->extractHttpStatus($http_response_header ?? []);

        return [
            'delivered' => $result !== false && $httpStatus >= 200 && $httpStatus < 300,
            'http_status' => $httpStatus,
        ];
    }

    private function extractHttpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }

            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function flattenExportRows(string $type, array $payload): array
    {
        if ($type === 'datev') {
            return is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        }

        if ($type === 'op') {
            return is_array($payload['items'] ?? null) ? $payload['items'] : [];
        }

        if ($type === 'tax') {
            return is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        }

        return [];
    }

    private function writeTabularStream(array $rows, string $format): void
    {
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new RuntimeException('export_stream_unavailable');
        }

        if ($rows === []) {
            fclose($out);
            return;
        }

        $delimiter = $format === 'excel' ? "\t" : ';';
        $header = array_keys((array) $rows[0]);
        fputcsv($out, $header, $delimiter);

        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $key) {
                $value = is_array($row) ? ($row[$key] ?? null) : null;
                $line[] = is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value, JSON_THROW_ON_ERROR);
            }
            fputcsv($out, $line, $delimiter);
        }

        fclose($out);
    }

    private function exportFilename(string $type, string $format): string
    {
        $extension = $format === 'excel' ? 'tsv' : 'csv';
        return sprintf('finance_%s_%s.%s', $type, date('Ymd_His'), $extension);
    }

    private function exportContentType(string $format): string
    {
        return $format === 'excel'
            ? 'text/tab-separated-values; charset=utf-8'
            : 'text/csv; charset=utf-8';
    }

    private function buildDatevRows(string $tenantId, ?string $fromDate, ?string $toDate): array
    {
        [$from, $to] = $this->resolveDateRange($fromDate, $toDate, '-30 days');
        $stmt = $this->pdo->prepare(
            "SELECT d.document_number, d.document_type, DATE(d.finalized_at) AS booking_date, d.grand_total,
                    COALESCE(SUM(CASE WHEN p.status = 'received' THEN p.amount ELSE 0 END), 0) AS paid_amount
             FROM billing_documents d
             LEFT JOIN billing_payments p ON p.tenant_id = d.tenant_id AND p.document_id = d.id
             WHERE d.tenant_id = :tenant_id
               AND d.finalized_at IS NOT NULL
               AND DATE(d.finalized_at) BETWEEN :from_date AND :to_date
             GROUP BY d.id, d.document_number, d.document_type, booking_date, d.grand_total
             ORDER BY booking_date ASC, d.id ASC"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'from_date' => $from, 'to_date' => $to]);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'belegnummer' => (string) ($row['document_number'] ?? ''),
                'belegtyp' => (string) ($row['document_type'] ?? ''),
                'buchungsdatum' => (string) ($row['booking_date'] ?? ''),
                'betrag' => round((float) ($row['grand_total'] ?? 0), 2),
                'offen' => round(max(0.0, (float) ($row['grand_total'] ?? 0) - (float) ($row['paid_amount'] ?? 0)), 2),
            ];
        }

        return [
            'range' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
        ];
    }

    private function openReceivablesTotal(string $tenantId, string $asOfDate): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(d.grand_total - COALESCE(p.paid_amount, 0)), 0) AS open_total
             FROM billing_documents d
             LEFT JOIN (
                 SELECT tenant_id, document_id, SUM(CASE WHEN status = 'received' THEN amount ELSE 0 END) AS paid_amount
                 FROM billing_payments
                 WHERE tenant_id = :tenant_id
                 GROUP BY tenant_id, document_id
             ) p ON p.tenant_id = d.tenant_id AND p.document_id = d.id
             WHERE d.tenant_id = :tenant_id
               AND d.document_type IN ('invoice', 'order_confirmation')
               AND d.finalized_at IS NOT NULL
               AND DATE(COALESCE(d.due_date, d.finalized_at)) <= :as_of_date
               AND COALESCE(p.paid_amount, 0) < d.grand_total"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'as_of_date' => $asOfDate]);

        return max(0.0, (float) (($stmt->fetch()['open_total'] ?? 0)));
    }

    private function mrrArr(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sp.billing_interval, sp.amount
             FROM subscription_contracts sc
             INNER JOIN subscription_plans sp ON sp.id = sc.plan_id AND sp.tenant_id = sc.tenant_id
             WHERE sc.tenant_id = :tenant_id
               AND sc.status = 'active'"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $mrr = 0.0;
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $interval = strtolower((string) ($row['billing_interval'] ?? 'monthly'));
            $mrr += $interval === 'yearly' ? ($amount / 12) : $amount;
        }

        return ['mrr' => round($mrr, 2), 'arr' => round($mrr * 12, 2)];
    }

    private function increaseAgingBucket(array &$summary, int $agingDays, float $amount): void
    {
        if ($agingDays <= 0) {
            $summary['bucket_current'] += $amount;
            return;
        }

        if ($agingDays <= 30) {
            $summary['bucket_1_30'] += $amount;
            return;
        }

        if ($agingDays <= 60) {
            $summary['bucket_31_60'] += $amount;
            return;
        }

        if ($agingDays <= 90) {
            $summary['bucket_61_90'] += $amount;
            return;
        }

        $summary['bucket_90_plus'] += $amount;
    }

    private function resolveDateRange(?string $fromDate, ?string $toDate, string $defaultFromModifier): array
    {
        $to = $this->normalizeDate($toDate) ?? date('Y-m-d');
        $from = $this->normalizeDate($fromDate) ?? date('Y-m-d', strtotime($defaultFromModifier));

        if (strtotime($from) > strtotime($to)) {
            throw new RuntimeException('invalid_date_range');
        }

        return [$from, $to];
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('invalid_date');
        }

        return date('Y-m-d', $timestamp);
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
