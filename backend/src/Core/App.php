<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\AdminPluginController;
use App\Controllers\CrudController;
use App\Controllers\DocumentController;
use App\Controllers\AccountManagementController;
use App\Controllers\StripeController;
use App\Controllers\UploadController;
use App\Controllers\PlatformAdminController;
use App\Services\JwtService;
use App\Services\PdfRendererService;
use App\Services\RefreshTokenService;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\RbacService;
use App\Services\StripeService;
use App\Services\TemplateRendererService;
use App\Services\TenantMailerService;

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
        $router->add('GET', '/api/admin/roles/permissions', [$adminPlugins, 'listRolePermissions']);
        $router->add('PUT', '/api/admin/roles/{roleKey}/permissions', [$adminPlugins, 'updateRolePermissions']);
        $router->add('GET', '/api/admin/approvals', [$adminPlugins, 'listApprovals']);
        $router->add('POST', '/api/admin/approvals/{approvalId}/approve', [$adminPlugins, 'approve']);
        $router->add('POST', '/api/admin/approvals/{approvalId}/reject', [$adminPlugins, 'reject']);

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

        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-Id, X-User-Id, X-Permissions, X-Approval-Status, Stripe-Signature');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
    }
}
