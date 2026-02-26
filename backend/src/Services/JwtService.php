<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

final class JwtService
{
    public function issueToken(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            ...$claims,
            'iat' => time(),
            'exp' => time() + (int) Env::get('JWT_TTL', '3600'),
        ], JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', "$header.$payload", Env::get('APP_KEY', 'change_me'), true);
        return $header . '.' . $payload . '.' . $this->base64UrlEncode($signature);
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", Env::get('APP_KEY', 'change_me'), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($data) || ($data['exp'] ?? 0) < time()) {
            return null;
        }

        return $data;
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }
}
