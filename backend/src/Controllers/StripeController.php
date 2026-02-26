<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\StripeService;

final class StripeController
{
    public function __construct(private readonly StripeService $stripeService)
    {
    }

    public function webhook(Request $request): void
    {
        $payload = file_get_contents('php://input') ?: '{}';
        $signature = $request->header('Stripe-Signature');

        $event = $this->stripeService->parseEvent($payload, $signature);
        $this->stripeService->handleWebhook($event);

        Response::json(['received' => true]);
    }
}
