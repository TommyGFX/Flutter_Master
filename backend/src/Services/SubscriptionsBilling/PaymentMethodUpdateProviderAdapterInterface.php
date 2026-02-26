<?php

declare(strict_types=1);

namespace App\Services\SubscriptionsBilling;

interface PaymentMethodUpdateProviderAdapterInterface
{
    public function providerKey(): string;

    /**
     * @param array<string, mixed> $contract
     * @param array<string, mixed> $payload
     * @return array{update_url:string,status:string,provider_response_json:string|null}
     */
    public function createUpdateLink(string $tenantId, int $contractId, string $token, array $contract, array $payload): array;
}
