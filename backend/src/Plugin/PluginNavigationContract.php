<?php

declare(strict_types=1);

namespace App\Plugin;

final class PluginNavigationContract
{
    /**
     * @param list<string> $grantedPermissions
     * @param list<string> $requiredPermissions
     */
    public static function hasCapabilityAccess(array $grantedPermissions, array $requiredPermissions): bool
    {
        if (in_array('*', $grantedPermissions, true) || $requiredPermissions === []) {
            return true;
        }

        foreach ($requiredPermissions as $requiredPermission) {
            if (!in_array($requiredPermission, $grantedPermissions, true)) {
                return false;
            }
        }

        return true;
    }

    public static function isVisibleInNavigation(string $lifecycleStatus, bool $isActive): bool
    {
        return $lifecycleStatus === 'enabled' && $isActive;
    }
}
