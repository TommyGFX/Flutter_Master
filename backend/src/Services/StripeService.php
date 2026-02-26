<?php

declare(strict_types=1);

namespace App\Services;

final class StripeService
{
    public function parseEvent(string $payload, ?string $signature): array
    {
        return [
            'signature_present' => $signature !== null,
            'event' => json_decode($payload, true) ?: [],
        ];
    }

    public function handleWebhook(array $event): void
    {
        // Hier später vollständige Stripe-Eventlogik für Checkout, Abo, Rechnungen, Kündigungen etc.
    }
}
