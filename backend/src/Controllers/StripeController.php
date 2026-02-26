<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Env;
use App\Services\StripeService;
use InvalidArgumentException;
use Stripe\Exception\ApiErrorException;
use Throwable;

final class StripeController
{
    public function __construct(private readonly StripeService $stripeService)
    {
    }

    public function createCheckoutSession(Request $request): void
    {
        try {
            $result = $this->stripeService->createCheckoutSession($request->json());
            Response::json(['data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        } catch (ApiErrorException $exception) {
            $this->logStripeException('checkout', $exception);
            Response::json(['error' => $this->resolveStripeErrorMessage($exception)], 422);
        } catch (Throwable $exception) {
            $this->logStripeException('checkout', $exception);
            Response::json(['error' => 'Stripe Checkout Session konnte nicht erstellt werden.'], 500);
        }
    }

    public function createCustomerPortalSession(Request $request): void
    {
        try {
            $result = $this->stripeService->createCustomerPortalSession($request->json());
            Response::json(['data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        } catch (ApiErrorException $exception) {
            $this->logStripeException('portal', $exception);
            Response::json(['error' => $this->resolveStripeErrorMessage($exception)], 422);
        } catch (Throwable $exception) {
            $this->logStripeException('portal', $exception);
            Response::json(['error' => 'Stripe Customer Portal Session konnte nicht erstellt werden.'], 500);
        }
    }

    public function webhook(Request $request): void
    {
        $payload = file_get_contents('php://input') ?: '{}';
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->parseEvent($payload, $signature);
            $this->stripeService->handleWebhook($event);
            Response::json(['received' => true]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 400);
        }
    }

    private function resolveStripeErrorMessage(ApiErrorException $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'Stripe konnte die Anfrage nicht verarbeiten.';
        }

        return Env::get('APP_DEBUG', 'false') === 'true'
            ? 'Stripe API Fehler: ' . $message
            : 'Stripe konnte die Anfrage nicht verarbeiten.';
    }

    private function logStripeException(string $action, Throwable $exception): void
    {
        $logPath = __DIR__ . '/../../storage/logs/error.log';
        $message = sprintf("[%s] stripe_%s_error %s\n", date('c'), $action, (string) $exception);
        @file_put_contents($logPath, $message, FILE_APPEND);
    }
}
