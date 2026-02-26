<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\JwtService;
use App\Services\RefreshTokenService;

final class AuthController
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly RefreshTokenService $refreshTokenService
    ) {
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
        $claims = [
            'tenant_id' => 'superadmin',
            'user_id' => $body['email'] ?? 'admin@local',
            'entrypoint' => 'admin',
            'permissions' => ['*'],
            'is_superadmin' => true,
        ];

        Response::json($this->issueAuthPayload($claims, $request) + ['role' => 'superadmin']);
    }

    public function refresh(Request $request): void
    {
        $refreshToken = $request->json()['refresh_token'] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            Response::json(['error' => 'missing_refresh_token'], 422);
            return;
        }

        $session = $this->refreshTokenService->consumeAndRotate($refreshToken, $request->ipAddress(), $request->userAgent());
        if (!is_array($session)) {
            Response::json(['error' => 'invalid_refresh_token'], 401);
            return;
        }

        $accessClaims = [
            'tenant_id' => $session['tenant_id'],
            'user_id' => $session['user_id'],
            'entrypoint' => $session['entrypoint'],
            'permissions' => $session['permissions'],
            'is_superadmin' => $session['is_superadmin'],
        ];

        $accessToken = $this->jwtService->issueToken($accessClaims);

        Response::json([
            'token' => $accessToken,
            'refresh_token' => $session['refresh_token'],
            'refresh_token_expires_at' => $session['refresh_token_expires_at'],
        ]);
    }

    public function logout(Request $request): void
    {
        $refreshToken = $request->json()['refresh_token'] ?? null;
        if (is_string($refreshToken) && $refreshToken !== '') {
            $this->refreshTokenService->revoke($refreshToken);
        }

        Response::json(['status' => 'ok']);
    }

    private function loginByType(Request $request, string $entry): void
    {
        $body = $request->json();
        $tenant = $body['tenant_id'] ?? 'default_tenant';

        $claims = [
            'tenant_id' => $tenant,
            'user_id' => $body['email'] ?? 'user@example.com',
            'entrypoint' => $entry,
            'permissions' => ['crud.read', 'crud.write', 'upload.file'],
            'is_superadmin' => false,
        ];

        Response::json($this->issueAuthPayload($claims, $request) + ['entrypoint' => $entry]);
    }

    private function issueAuthPayload(array $claims, Request $request): array
    {
        $accessToken = $this->jwtService->issueToken($claims);
        $refreshToken = $this->refreshTokenService->issue($claims, $request->ipAddress(), $request->userAgent());

        return [
            'token' => $accessToken,
            'tenant_id' => $claims['tenant_id'] ?? null,
            'permissions' => $claims['permissions'] ?? [],
            ...$refreshToken,
        ];
    }
}
