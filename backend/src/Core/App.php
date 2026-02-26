<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\CrudController;
use App\Controllers\StripeController;
use App\Controllers\UploadController;
use App\Services\JwtService;
use App\Services\RefreshTokenService;
use App\Services\StripeService;

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

        $router->add('POST', '/api/stripe/webhook', [$stripe, 'webhook']);

        $router->dispatch($request);
    }
}
