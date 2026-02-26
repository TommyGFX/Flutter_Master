<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }


    public function query(?string $key = null): mixed
    {
        $queryString = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        if (!is_string($queryString) || $queryString === '') {
            return $key === null ? [] : null;
        }

        parse_str($queryString, $params);
        if ($key === null) {
            return $params;
        }

        return $params[$key] ?? null;
    }

    public function json(): array
    {
        $raw = file_get_contents('php://input') ?: '{}';
        return json_decode($raw, true) ?: [];
    }

    public function header(string $name): ?string
    {
        $normalized = strtoupper(str_replace('-', '_', $name));
        $candidates = [
            'HTTP_' . $normalized,
            $normalized,
            'REDIRECT_HTTP_' . $normalized,
        ];

        foreach ($candidates as $candidate) {
            $value = $_SERVER[$candidate] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        if (function_exists('getallheaders')) {
            $target = strtolower($name);
            foreach (getallheaders() as $headerName => $headerValue) {
                if (strtolower((string) $headerName) !== $target) {
                    continue;
                }

                if (is_string($headerValue) && trim($headerValue) !== '') {
                    return trim($headerValue);
                }
            }
        }

        return null;
    }
    public function ipAddress(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

}
