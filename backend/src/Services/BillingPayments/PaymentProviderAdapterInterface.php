<?php

declare(strict_types=1);

namespace App\Services\BillingPayments;

interface PaymentProviderAdapterInterface
{
    public function providerKey(): string;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $document
     * @return array{payment_link_id:string,payment_url:string,status:string,provider_response_json:string|null,expires_at:?string}
     */
    public function createPaymentLink(array $payload, array $document): array;
}
