<?php

declare(strict_types=1);

namespace App\Plugin;

use InvalidArgumentException;

final class PluginContract
{
    public const ALLOWED_HOOKS = [
        'before_validate',
        'before_finalize',
        'after_finalize',
        'before_send',
        'after_payment',
    ];

    public const LIFECYCLE_STATES = ['installed', 'enabled', 'suspended', 'retired'];

    private const LIFECYCLE_TRANSITIONS = [
        'installed' => ['enabled'],
        'enabled' => ['suspended', 'retired'],
        'suspended' => ['enabled', 'retired'],
        'retired' => [],
    ];

    public static function assertValidMetadata(string $pluginKey, string $version, array $capabilities, array $requiredPermissions): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $pluginKey)) {
            throw new InvalidArgumentException('invalid_plugin_key');
        }

        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException('invalid_plugin_version');
        }

        self::assertStringList($capabilities, 'invalid_capabilities');
        self::assertStringList($requiredPermissions, 'invalid_required_permissions');
    }

    public static function isAllowedHook(string $hookName): bool
    {
        return in_array($hookName, self::ALLOWED_HOOKS, true);
    }

    public static function assertLifecycleState(string $status): void
    {
        if (!in_array($status, self::LIFECYCLE_STATES, true)) {
            throw new InvalidArgumentException('invalid_lifecycle_status');
        }
    }

    public static function assertLifecycleTransition(string $fromStatus, string $toStatus): void
    {
        self::assertLifecycleState($fromStatus);
        self::assertLifecycleState($toStatus);

        if ($fromStatus === $toStatus) {
            return;
        }

        $allowedTargets = self::LIFECYCLE_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($toStatus, $allowedTargets, true)) {
            throw new InvalidArgumentException('invalid_lifecycle_transition');
        }
    }

    private static function assertStringList(array $values, string $error): void
    {
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException($error);
            }
        }
    }
}

