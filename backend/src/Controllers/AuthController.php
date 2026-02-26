<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\JwtService;

final class AuthController
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function loginCompany(Request $request): void
    {
        $this->loginByType($request, 'company');
    }

    public function loginEmployee(Request $request): void
    {
        $this->loginByType($request, 'employee');
    }

    public function loginPortal(Request $request): void
    {
        $this->loginByType($request, 'customer');
    }

    public function loginAdmin(Request $request): void
    {
        $body = $request->json();
        $token = $this->jwtService->issueToken([
            'tenant_id' => 'superadmin',
            'user_id' => $body['email'] ?? 'admin@local',
            'entrypoint' => 'admin',
            'permissions' => ['*'],
            'is_superadmin' => true,
        ]);

        Response::json(['token' => $token, 'role' => 'superadmin']);
    }

    private function loginByType(Request $request, string $entry): void
    {
        $body = $request->json();
        $tenant = $body['tenant_id'] ?? 'default_tenant';
        $token = $this->jwtService->issueToken([
            'tenant_id' => $tenant,
            'user_id' => $body['email'] ?? 'user@example.com',
            'entrypoint' => $entry,
            'permissions' => ['crud.read', 'crud.write', 'upload.file'],
            'is_superadmin' => false,
        ]);

        Response::json(['token' => $token, 'entrypoint' => $entry]);
    }
}
