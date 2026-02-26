<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BillingCoreService;
use App\Services\PdfRendererService;
use RuntimeException;

final class BillingCoreController
{
    public function __construct(
        private readonly BillingCoreService $billing,
        private readonly PdfRendererService $pdfRendererService
    ) {
    }

    public function listDocuments(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->billing->listDocuments($tenantId)]);
    }

    public function getDocument(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $document = $this->billing->getDocument($tenantId, (int) $documentId);
        if ($document === null) {
            Response::json(['error' => 'document_not_found'], 404);
            return;
        }

        Response::json(['data' => $document]);
    }

    public function createDocument(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $documentId = $this->billing->saveDraft($tenantId, $request->json());
            Response::json(['document_id' => $documentId, 'status' => 'draft'], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function updateDocument(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $savedDocumentId = $this->billing->saveDraft($tenantId, $request->json(), (int) $documentId);
            Response::json(['document_id' => $savedDocumentId, 'status' => 'draft']);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function finalizeDocument(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $result = $this->billing->finalizeDocument($tenantId, (int) $documentId);
            Response::json($result);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function convertToInvoice(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $newDocumentId = $this->billing->convertQuoteToInvoice($tenantId, (int) $documentId);
            Response::json(['document_id' => $newDocumentId, 'document_type' => 'invoice', 'status' => 'draft'], 201);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function createCreditNote(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $newDocumentId = $this->billing->createCreditNote($tenantId, (int) $documentId, $request->json());
            Response::json(['document_id' => $newDocumentId, 'document_type' => 'credit_note', 'status' => 'draft'], 201);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function setStatus(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $status = is_string($request->json()['status'] ?? null) ? trim((string) $request->json()['status']) : '';

        try {
            $this->billing->setStatus($tenantId, (int) $documentId, $status);
            Response::json(['document_id' => (int) $documentId, 'status' => $status]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }


    public function exportPdf(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $document = $this->billing->getDocument($tenantId, (int) $documentId);
        if ($document === null) {
            Response::json(['error' => 'document_not_found'], 404);
            return;
        }

        $lineRows = '';
        foreach ($document['line_items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $lineRows .= sprintf(
                '<tr><td>%s</td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td></tr>',
                htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                number_format((float) ($item['quantity'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['unit_price'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['line_net'] ?? 0), 2, ',', '.')
            );
        }

        $html = sprintf(
            '<html><body><h1>%s %s</h1><p>Kunde: %s</p><table width="100%%" border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Position</th><th>Menge</th><th>Einzelpreis</th><th>Netto</th></tr></thead><tbody>%s</tbody></table><p><strong>Gesamt: %s %s</strong></p></body></html>',
            strtoupper((string) ($document['document_type'] ?? 'document')),
            htmlspecialchars((string) ($document['document_number'] ?? 'Entwurf'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) ($document['customer_name_snapshot'] ?? '-'), ENT_QUOTES, 'UTF-8'),
            $lineRows,
            number_format((float) ($document['grand_total'] ?? 0), 2, ',', '.'),
            htmlspecialchars((string) ($document['currency_code'] ?? 'EUR'), ENT_QUOTES, 'UTF-8')
        );

        $binary = $this->pdfRendererService->render($html);

        Response::json([
            'filename' => sprintf('billing-document-%d.pdf', (int) $documentId),
            'mime' => 'application/pdf',
            'content_base64' => base64_encode($binary),
        ]);
    }

    public function history(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->billing->history($tenantId, (int) $documentId)]);
    }

    public function listCustomers(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->billing->listCustomers($tenantId)]);
    }

    public function createCustomer(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $customerId = $this->billing->saveCustomer($tenantId, $request->json());
        Response::json(['customer_id' => $customerId], 201);
    }

    public function updateCustomer(Request $request, string $customerId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $savedId = $this->billing->saveCustomer($tenantId, $request->json(), (int) $customerId);
        Response::json(['customer_id' => $savedId]);
    }

    private function tenantId(Request $request): ?string
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (!is_string($tenantId) || trim($tenantId) === '') {
            Response::json(['error' => 'missing_tenant_header'], 422);
            return null;
        }

        return trim($tenantId);
    }
}
