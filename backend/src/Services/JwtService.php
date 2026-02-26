<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    public function issueToken(array $claims): string
    {
        $now = time();

        return JWT::encode([
            ...$claims,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) Env::get('JWT_TTL', '3600'),
        ], $this->secret(), 'HS256');
    }

    public function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret(), 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException) {
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function secret(): string
    {
        return Env::get('JWT_SECRET', Env::get('APP_KEY', 'change_me')) ?? 'change_me';
    }
}
