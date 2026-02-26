<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\AdminPluginController;
use App\Controllers\CrudController;
use App\Controllers\DocumentController;
use App\Controllers\BillingCoreController;
use App\Controllers\BillingPaymentsController;
use App\Controllers\TaxComplianceDeController;
use App\Controllers\SubscriptionsBillingController;
use App\Controllers\DocumentDeliveryController;
use App\Controllers\FinanceReportingController;
use App\Controllers\OrgManagementController;
use App\Controllers\AccountManagementController;
use App\Controllers\StripeController;
use App\Controllers\UploadController;
use App\Controllers\PlatformAdminController;
use App\Controllers\PluginFoundationController;
use App\Controllers\AutomationIntegrationsController;
use App\Controllers\CatalogPricingController;
use App\Controllers\PlatformSecurityOpsController;
use App\Services\JwtService;
use App\Services\PdfRendererService;
use App\Services\RefreshTokenService;
use App\Services\ApprovalService;
use App\Services\BillingCoreService;
use App\Services\BillingPaymentsService;
use App\Services\TaxComplianceDeService;
use App\Services\SubscriptionsBillingService;
use App\Services\DocumentDeliveryService;
use App\Services\FinanceReportingService;
use App\Services\OrgManagementService;
use App\Services\AuditLogService;
use App\Services\RbacService;
use App\Services\StripeService;
use App\Services\TemplateRendererService;
use App\Services\TenantMailerService;
use App\Services\AutomationIntegrationsService;
use App\Services\CatalogPricingService;
use App\Services\PlatformSecurityOpsService;

final class App
{
    public function run(): void
    {
        $router = new Router();
        $request = new Request();

        $this->applyCors($request);

        if (strtoupper($request->method()) === 'OPTIONS') {
            Response::json(['ok' => true]);
            return;
        }

        $auth = new AuthController(new JwtService(), new RefreshTokenService());
        $crud = new CrudController();
        $upload = new UploadController();
        $stripe = new StripeController(new StripeService());
        $document = new DocumentController(new PdfRendererService(), new TenantMailerService(), new TemplateRendererService());
        $adminPlugins = new AdminPluginController(new RbacService(), new ApprovalService(), new AuditLogService());
        $platformAdmin = new PlatformAdminController(new JwtService(), new RefreshTokenService(), new AuditLogService());
        $accounts = new AccountManagementController();
        $pluginFoundation = new PluginFoundationController(new RbacService());
        $billingCore = new BillingCoreController(new BillingCoreService(Database::connection()), new PdfRendererService());
        $billingPayments = new BillingPaymentsController(new BillingPaymentsService(Database::connection()));
        $taxComplianceDe = new TaxComplianceDeController(new TaxComplianceDeService(Database::connection(), new BillingCoreService(Database::connection())));
        $subscriptionsBilling = new SubscriptionsBillingController(new SubscriptionsBillingService(Database::connection()));
        $documentDelivery = new DocumentDeliveryController(new DocumentDeliveryService(Database::connection()));
        $financeReporting = new FinanceReportingController(new FinanceReportingService(Database::connection()));
        $orgManagement = new OrgManagementController(new OrgManagementService(Database::connection()), new RbacService(), new AuditLogService());
        $automationIntegrations = new AutomationIntegrationsController(new AutomationIntegrationsService(Database::connection()));
        $catalogPricing = new CatalogPricingController(new CatalogPricingService(Database::connection()));
        $platformSecurityOps = new PlatformSecurityOpsController(new PlatformSecurityOpsService(Database::connection()));

        $router->add('POST', '/api/login/company', [$auth, 'loginCompany']);
        $router->add('POST', '/api/login/employee', [$auth, 'loginEmployee']);
        $router->add('POST', '/api/login/portal', [$auth, 'loginPortal']);
        $router->add('POST', '/api/admin/login', [$auth, 'loginAdmin']);
        $router->add('POST', '/api/token/refresh', [$auth, 'refresh']);
        $router->add('POST', '/api/logout', [$auth, 'logout']);

        $router->add('GET', '/api/crud/{resource}', [$crud, 'index']);
        $router->add('POST', '/api/crud/{resource}', [$crud, 'store']);
        $router->add('PUT', '/api/crud/{resource}/{id}', [$crud, 'update']);
        $router->add('DELETE', '/api/crud/{resource}/{id}', [$crud, 'destroy']);

        $router->add('POST', '/api/upload/image', [$upload, 'uploadImage']);
        $router->add('POST', '/api/upload/file', [$upload, 'uploadFile']);

        $router->add('POST', '/api/stripe/checkout-session', [$stripe, 'createCheckoutSession']);
        $router->add('POST', '/api/stripe/customer-portal', [$stripe, 'createCustomerPortalSession']);
        $router->add('POST', '/api/stripe/webhook', [$stripe, 'webhook']);

        $router->add('POST', '/api/pdf/render', [$document, 'renderPdf']);
        $router->add('POST', '/api/email/send', [$document, 'sendEmail']);

        $router->add('GET', '/api/admin/plugins', [$adminPlugins, 'index']);
        $router->add('POST', '/api/admin/plugins/{plugin}/status', [$adminPlugins, 'setStatus']);
        $router->add('PUT', '/api/admin/plugins/{plugin}/lifecycle', [$pluginFoundation, 'updateLifecycle']);
        $router->add('GET', '/api/admin/roles/permissions', [$adminPlugins, 'listRolePermissions']);
        $router->add('PUT', '/api/admin/roles/{roleKey}/permissions', [$adminPlugins, 'updateRolePermissions']);
        $router->add('GET', '/api/admin/approvals', [$adminPlugins, 'listApprovals']);
        $router->add('POST', '/api/admin/approvals/{approvalId}/approve', [$adminPlugins, 'approve']);
        $router->add('POST', '/api/admin/approvals/{approvalId}/reject', [$adminPlugins, 'reject']);
        $router->add('GET', '/api/admin/plugin-shell', [$pluginFoundation, 'pluginShell']);
        $router->add('GET', '/api/admin/feature-flags', [$pluginFoundation, 'listFeatureFlags']);
        $router->add('PUT', '/api/admin/feature-flags/{flagKey}', [$pluginFoundation, 'setFeatureFlag']);
        $router->add('POST', '/api/admin/domain-events', [$pluginFoundation, 'publishDomainEvent']);
        $router->add('POST', '/api/admin/outbox/process', [$pluginFoundation, 'processOutbox']);

        $router->add('POST', '/api/platform/impersonate/company', [$platformAdmin, 'impersonateCompany']);
        $router->add('GET', '/api/platform/admin-stats', [$platformAdmin, 'adminStats']);
        $router->add('GET', '/api/platform/audit-logs', [$platformAdmin, 'globalAuditLogs']);
        $router->add('GET', '/api/platform/reports', [$platformAdmin, 'platformReports']);

        $router->add('GET', '/api/admin/users', [$accounts, 'listUsers']);
        $router->add('POST', '/api/admin/users', [$accounts, 'createUser']);
        $router->add('PUT', '/api/admin/users/{id}', [$accounts, 'updateUser']);
        $router->add('DELETE', '/api/admin/users/{id}', [$accounts, 'deleteUser']);

        $router->add('GET', '/api/customers', [$accounts, 'listCustomers']);
        $router->add('POST', '/api/customers', [$accounts, 'createCustomer']);
        $router->add('PUT', '/api/customers/{id}', [$accounts, 'updateCustomer']);
        $router->add('DELETE', '/api/customers/{id}', [$accounts, 'deleteCustomer']);


        $router->add('GET', '/api/billing/documents', [$billingCore, 'listDocuments']);
        $router->add('POST', '/api/billing/documents', [$billingCore, 'createDocument']);
        $router->add('GET', '/api/billing/documents/{id}', [$billingCore, 'getDocument']);
        $router->add('PUT', '/api/billing/documents/{id}', [$billingCore, 'updateDocument']);
        $router->add('POST', '/api/billing/documents/{id}/finalize', [$billingCore, 'finalizeDocument']);
        $router->add('POST', '/api/billing/documents/{id}/convert-to-invoice', [$billingCore, 'convertToInvoice']);
        $router->add('POST', '/api/billing/documents/{id}/credit-note', [$billingCore, 'createCreditNote']);
        $router->add('POST', '/api/billing/documents/{id}/status', [$billingCore, 'setStatus']);
        $router->add('GET', '/api/billing/documents/{id}/history', [$billingCore, 'history']);
        $router->add('GET', '/api/billing/documents/{id}/pdf', [$billingCore, 'exportPdf']);


        $router->add('GET', '/api/billing/documents/{id}/payment-links', [$billingPayments, 'listPaymentLinks']);
        $router->add('POST', '/api/billing/documents/{id}/payment-links', [$billingPayments, 'createPaymentLink']);
        $router->add('GET', '/api/billing/documents/{id}/payments', [$billingPayments, 'listPayments']);
        $router->add('POST', '/api/billing/documents/{id}/payments', [$billingPayments, 'recordPayment']);

        $router->add('GET', '/api/billing/dunning/config', [$billingPayments, 'getDunningConfig']);
        $router->add('PUT', '/api/billing/dunning/config', [$billingPayments, 'saveDunningConfig']);
        $router->add('POST', '/api/billing/dunning/run', [$billingPayments, 'runDunning']);
        $router->add('GET', '/api/billing/dunning/cases', [$billingPayments, 'listDunningCases']);

        $router->add('GET', '/api/billing/bank-account', [$billingPayments, 'getBankAccount']);
        $router->add('PUT', '/api/billing/bank-account', [$billingPayments, 'saveBankAccount']);

        $router->add('GET', '/api/billing/tax-compliance/config', [$taxComplianceDe, 'getConfig']);
        $router->add('PUT', '/api/billing/tax-compliance/config', [$taxComplianceDe, 'saveConfig']);
        $router->add('POST', '/api/billing/tax-compliance/documents/{id}/preflight', [$taxComplianceDe, 'preflight']);
        $router->add('POST', '/api/billing/tax-compliance/documents/{id}/seal', [$taxComplianceDe, 'seal']);
        $router->add('POST', '/api/billing/tax-compliance/documents/{id}/correction', [$taxComplianceDe, 'createCorrection']);
        $router->add('GET', '/api/billing/tax-compliance/documents/{id}/e-invoice/export', [$taxComplianceDe, 'exportEInvoice']);
        $router->add('POST', '/api/billing/tax-compliance/e-invoice/import', [$taxComplianceDe, 'importEInvoice']);

        $router->add('GET', '/api/billing/subscriptions/plans', [$subscriptionsBilling, 'listPlans']);
        $router->add('POST', '/api/billing/subscriptions/plans', [$subscriptionsBilling, 'savePlan']);
        $router->add('GET', '/api/billing/subscriptions/contracts', [$subscriptionsBilling, 'listContracts']);
        $router->add('POST', '/api/billing/subscriptions/contracts', [$subscriptionsBilling, 'createContract']);
        $router->add('PUT', '/api/billing/subscriptions/contracts/{id}', [$subscriptionsBilling, 'updateContract']);
        $router->add('POST', '/api/billing/subscriptions/contracts/{id}/change-plan', [$subscriptionsBilling, 'changePlan']);
        $router->add('POST', '/api/billing/subscriptions/run-recurring', [$subscriptionsBilling, 'runRecurring']);
        $router->add('POST', '/api/billing/subscriptions/auto-invoicing/run', [$subscriptionsBilling, 'runAutoInvoicing']);
        $router->add('POST', '/api/billing/subscriptions/dunning/run', [$subscriptionsBilling, 'runDunningRetention']);
        $router->add('POST', '/api/billing/subscriptions/contracts/{id}/payment-method-update-link', [$subscriptionsBilling, 'createPaymentMethodUpdateLink']);

        $router->add('GET', '/api/billing/delivery/templates', [$documentDelivery, 'listTemplates']);
        $router->add('PUT', '/api/billing/delivery/templates/{templateKey}', [$documentDelivery, 'upsertTemplate']);
        $router->add('GET', '/api/billing/delivery/provider', [$documentDelivery, 'getProviderConfig']);
        $router->add('PUT', '/api/billing/delivery/provider', [$documentDelivery, 'upsertProviderConfig']);
        $router->add('GET', '/api/portal/documents', [$documentDelivery, 'listPortalDocuments']);
        $router->add('GET', '/api/portal/documents/{id}', [$documentDelivery, 'getPortalDocument']);
        $router->add('POST', '/api/billing/delivery/tracking/events', [$documentDelivery, 'trackEvent']);

        $router->add('GET', '/api/billing/finance/kpis', [$financeReporting, 'kpis']);
        $router->add('GET', '/api/billing/finance/op-list', [$financeReporting, 'openItems']);
        $router->add('GET', '/api/billing/finance/tax-report', [$financeReporting, 'taxReport']);
        $router->add('POST', '/api/billing/finance/exports', [$financeReporting, 'export']);
        $router->add('GET', '/api/billing/finance/connectors', [$financeReporting, 'listConnectors']);
        $router->add('PUT', '/api/billing/finance/connectors', [$financeReporting, 'upsertConnector']);
        $router->add('POST', '/api/billing/finance/connectors/{provider}/webhook', [$financeReporting, 'publishWebhook']);


        $router->add('GET', '/api/org/companies', [$orgManagement, 'listCompanies']);
        $router->add('POST', '/api/org/companies', [$orgManagement, 'upsertCompany']);
        $router->add('PUT', '/api/org/companies/{companyId}/memberships', [$orgManagement, 'assignMembership']);
        $router->add('POST', '/api/org/context/switch', [$orgManagement, 'switchContext']);
        $router->add('GET', '/api/org/roles', [$orgManagement, 'listRoles']);
        $router->add('PUT', '/api/org/roles/{roleKey}', [$orgManagement, 'upsertRole']);
        $router->add('GET', '/api/org/audit-logs', [$orgManagement, 'listAuditLogs']);
        $router->add('POST', '/api/org/audit-logs/export', [$orgManagement, 'exportAuditLogs']);


        $router->add('GET', '/api/billing/automation/api-versions', [$automationIntegrations, 'listApiVersions']);
        $router->add('POST', '/api/billing/automation/api-versions', [$automationIntegrations, 'registerApiVersion']);
        $router->add('POST', '/api/billing/automation/idempotency/claim', [$automationIntegrations, 'claimIdempotency']);
        $router->add('GET', '/api/billing/automation/crm/connectors', [$automationIntegrations, 'listCrmConnectors']);
        $router->add('PUT', '/api/billing/automation/crm/connectors', [$automationIntegrations, 'upsertCrmConnector']);
        $router->add('POST', '/api/billing/automation/crm/{provider}/sync', [$automationIntegrations, 'syncCrmEntity']);
        $router->add('GET', '/api/billing/automation/time-entries', [$automationIntegrations, 'listTimeEntries']);
        $router->add('POST', '/api/billing/automation/time-entries', [$automationIntegrations, 'upsertTimeEntry']);
        $router->add('POST', '/api/billing/automation/time-entries/invoice', [$automationIntegrations, 'invoiceTimeEntries']);
        $router->add('GET', '/api/billing/automation/workflows/catalog', [$automationIntegrations, 'listAutomationCatalog']);
        $router->add('POST', '/api/billing/automation/workflows/runs', [$automationIntegrations, 'enqueueAutomationRun']);
        $router->add('POST', '/api/billing/automation/import/preview', [$automationIntegrations, 'importPreview']);
        $router->add('POST', '/api/billing/automation/import/execute', [$automationIntegrations, 'executeImport']);

        $router->add('GET', '/api/billing/catalog/products', [$catalogPricing, 'listProducts']);
        $router->add('POST', '/api/billing/catalog/products', [$catalogPricing, 'saveProduct']);
        $router->add('PUT', '/api/billing/catalog/products/{id}', [$catalogPricing, 'updateProduct']);
        $router->add('GET', '/api/billing/catalog/price-lists', [$catalogPricing, 'listPriceLists']);
        $router->add('POST', '/api/billing/catalog/price-lists', [$catalogPricing, 'savePriceList']);
        $router->add('PUT', '/api/billing/catalog/price-lists/{id}', [$catalogPricing, 'updatePriceList']);
        $router->add('GET', '/api/billing/catalog/price-lists/{id}/items', [$catalogPricing, 'listPriceListItems']);
        $router->add('POST', '/api/billing/catalog/price-lists/{id}/items', [$catalogPricing, 'savePriceListItem']);
        $router->add('GET', '/api/billing/catalog/bundles', [$catalogPricing, 'listBundles']);
        $router->add('POST', '/api/billing/catalog/bundles', [$catalogPricing, 'saveBundle']);
        $router->add('PUT', '/api/billing/catalog/bundles/{id}', [$catalogPricing, 'updateBundle']);
        $router->add('GET', '/api/billing/catalog/discount-codes', [$catalogPricing, 'listDiscountCodes']);
        $router->add('POST', '/api/billing/catalog/discount-codes', [$catalogPricing, 'saveDiscountCode']);
        $router->add('POST', '/api/billing/catalog/quotes/calculate', [$catalogPricing, 'calculateQuote']);

        $router->add('GET', '/api/platform/security/gdpr', [$platformSecurityOps, 'gdprOverview']);
        $router->add('PUT', '/api/platform/security/gdpr/retention-rules', [$platformSecurityOps, 'upsertRetentionRule']);
        $router->add('POST', '/api/platform/security/gdpr/exports', [$platformSecurityOps, 'requestDataExport']);
        $router->add('POST', '/api/platform/security/gdpr/deletions', [$platformSecurityOps, 'requestDeletion']);
        $router->add('GET', '/api/platform/security/auth-policies', [$platformSecurityOps, 'listAuthPolicies']);
        $router->add('PUT', '/api/platform/security/auth-policies', [$platformSecurityOps, 'upsertAuthPolicy']);
        $router->add('GET', '/api/platform/security/backups', [$platformSecurityOps, 'listBackups']);
        $router->add('POST', '/api/platform/security/backups', [$platformSecurityOps, 'triggerBackup']);
        $router->add('POST', '/api/platform/security/backups/restore', [$platformSecurityOps, 'restoreBackup']);
        $router->add('GET', '/api/platform/security/archive-records', [$platformSecurityOps, 'listArchiveRecords']);
        $router->add('POST', '/api/platform/security/archive-records', [$platformSecurityOps, 'createArchiveRecord']);
        $router->add('GET', '/api/platform/security/reliability/policies', [$platformSecurityOps, 'listReliabilityPolicies']);
        $router->add('PUT', '/api/platform/security/reliability/policies', [$platformSecurityOps, 'upsertReliabilityPolicy']);

        $router->add('GET', '/api/billing/customers', [$billingCore, 'listCustomers']);
        $router->add('POST', '/api/billing/customers', [$billingCore, 'createCustomer']);
        $router->add('PUT', '/api/billing/customers/{id}', [$billingCore, 'updateCustomer']);
        $router->add('GET', '/api/self/profile', [$accounts, 'selfProfile']);
        $router->add('PUT', '/api/self/profile', [$accounts, 'updateSelfProfile']);

        $router->dispatch($request);
    }

    private function applyCors(Request $request): void
    {
        $allowedOrigins = [
            'https://crm.ordentis.de',
            'http://localhost:3000',
            'http://localhost:5173',
        ];

        $origin = $request->header('Origin');
        if ($origin !== null && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-Id, X-Company-Id, X-User-Id, X-Permissions, X-Approval-Status, Stripe-Signature');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
    }
}
