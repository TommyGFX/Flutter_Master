<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

final class RefreshTokenService
{
    public function issue(array $sessionClaims, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $token = $this->generateToken();
        $tokenId = $this->generateTokenId();
        $expiresAt = date('Y-m-d H:i:s', time() + (int) Env::get('REFRESH_TOKEN_TTL', '2592000'));

        $stmt = Database::connection()->prepare(
            'INSERT INTO refresh_tokens (token_id, token_hash, tenant_id, user_id, entrypoint, permissions_json, is_superadmin, expires_at, ip_address, user_agent)
             VALUES (:token_id, :token_hash, :tenant_id, :user_id, :entrypoint, :permissions_json, :is_superadmin, :expires_at, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'token_id' => $tokenId,
            'token_hash' => $this->hash($token),
            'tenant_id' => $sessionClaims['tenant_id'],
            'user_id' => $sessionClaims['user_id'],
            'entrypoint' => $sessionClaims['entrypoint'],
            'permissions_json' => json_encode($sessionClaims['permissions'] ?? [], JSON_THROW_ON_ERROR),
            'is_superadmin' => (int) ($sessionClaims['is_superadmin'] ?? false),
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return [
            'refresh_token' => $token,
            'refresh_token_expires_at' => $expiresAt,
        ];
    }

    public function consumeAndRotate(string $refreshToken, ?string $ipAddress = null, ?string $userAgent = null): ?array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM refresh_tokens WHERE token_hash = :token_hash LIMIT 1 FOR UPDATE');
        $stmt->execute(['token_hash' => $this->hash($refreshToken)]);
        $row = $stmt->fetch();

        if (!is_array($row) || (int) $row['revoked'] === 1 || strtotime((string) $row['expires_at']) <= time()) {
            $pdo->rollBack();
            return null;
        }

        $revoke = $pdo->prepare('UPDATE refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE id = :id');
        $revoke->execute(['id' => $row['id']]);

        $claims = [
            'tenant_id' => $row['tenant_id'],
            'user_id' => $row['user_id'],
            'entrypoint' => $row['entrypoint'],
            'permissions' => json_decode((string) ($row['permissions_json'] ?? '[]'), true) ?: [],
            'is_superadmin' => (bool) $row['is_superadmin'],
        ];

        $rotated = $this->issue($claims, $ipAddress, $userAgent);
        $pdo->commit();

        return [
            ...$claims,
            ...$rotated,
        ];
    }

    public function revoke(string $refreshToken): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE token_hash = :token_hash AND revoked = 0'
        );
        $stmt->execute(['token_hash' => $this->hash($refreshToken)]);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function generateTokenId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
