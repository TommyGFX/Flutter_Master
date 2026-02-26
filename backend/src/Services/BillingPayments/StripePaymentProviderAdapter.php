<?php

declare(strict_types=1);

namespace App\Services\BillingPayments;

use RuntimeException;

final class StripePaymentProviderAdapter implements PaymentProviderAdapterInterface
{
    public function providerKey(): string
    {
        return 'stripe';
    }

    public function createPaymentLink(array $payload, array $document): array
    {
        $paymentLinkId = trim((string) ($payload['payment_link_id'] ?? ''));
        $paymentUrl = trim((string) ($payload['url'] ?? ''));

        if ($paymentLinkId === '' || $paymentUrl === '') {
            throw new RuntimeException('stripe_payment_link_payload_invalid');
        }

        return [
            'payment_link_id' => $paymentLinkId,
            'payment_url' => $paymentUrl,
            'status' => $this->normalizeStatus($payload['status'] ?? 'open'),
            'provider_response_json' => $this->encodeProviderResponse($payload['provider_response'] ?? null),
            'expires_at' => $this->nullableString($payload['expires_at'] ?? null),
        ];
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if (!in_array($status, ['open', 'paid', 'expired', 'cancelled'], true)) {
            throw new RuntimeException('invalid_payment_link_status');
        }

        return $status;
    }

    private function encodeProviderResponse(mixed $response): ?string
    {
        if (!is_array($response)) {
            return null;
        }

        return json_encode($response);
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
