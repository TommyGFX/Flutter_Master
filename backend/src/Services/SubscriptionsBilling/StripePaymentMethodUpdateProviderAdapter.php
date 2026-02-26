<?php

declare(strict_types=1);

namespace App\Services\SubscriptionsBilling;

final class StripePaymentMethodUpdateProviderAdapter implements PaymentMethodUpdateProviderAdapterInterface
{
    public function providerKey(): string
    {
        return 'stripe';
    }

    public function createUpdateLink(string $tenantId, int $contractId, string $token, array $contract, array $payload): array
    {
        $baseUrl = trim((string) ($payload['base_url'] ?? 'https://billing.ordentis.de'));
        $query = http_build_query([
            'tenant' => $tenantId,
            'contract' => $contractId,
            'token' => $token,
            'provider' => $this->providerKey(),
        ]);

        return [
            'update_url' => rtrim($baseUrl, '/') . '/stripe/payment-method-update?' . $query,
            'status' => 'open',
            'provider_response_json' => json_encode([
                'provider' => $this->providerKey(),
                'payment_method_ref' => $contract['payment_method_ref'] ?? null,
            ]),
        ];
    }
}
