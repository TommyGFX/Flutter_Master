<?php

declare(strict_types=1);

namespace App\Services\BillingPayments;

use RuntimeException;

final class PaymentProviderRegistry
{
    /** @var array<string, PaymentProviderAdapterInterface> */
    private array $providers = [];

    /**
     * @param PaymentProviderAdapterInterface[] $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->providerKey()] = $provider;
        }
    }

    public function resolve(string $provider): PaymentProviderAdapterInterface
    {
        $key = strtolower(trim($provider));
        $adapter = $this->providers[$key] ?? null;
        if ($adapter === null) {
            throw new RuntimeException('invalid_provider');
        }

        return $adapter;
    }

    /**
     * @return string[]
     */
    public function availableProviders(): array
    {
        return array_keys($this->providers);
    }
}
