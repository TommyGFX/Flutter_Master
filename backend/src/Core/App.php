<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\AdminPluginController;
use App\Controllers\CrudController;
use App\Controllers\DocumentController;
use App\Controllers\StripeController;
use App\Controllers\UploadController;
use App\Services\JwtService;
use App\Services\PdfRendererService;
use App\Services\RefreshTokenService;
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

        $auth = new AuthController(new JwtService(), new RefreshTokenService());
        $crud = new CrudController();
        $upload = new UploadController();
        $stripe = new StripeController(new StripeService());
        $document = new DocumentController(new PdfRendererService(), new TenantMailerService(), new TemplateRendererService());
        $adminPlugins = new AdminPluginController(new RbacService());

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

        $router->dispatch($request);
    }
}
