<?php

declare(strict_types=1);

use App\Plugin\PluginNavigationContract;

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = __DIR__ . '/../../src/' . $relative . '.php';
        if (file_exists($path)) {
            require $path;
        }
    });
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertTrue(
    PluginNavigationContract::hasCapabilityAccess(['plugins.manage'], []),
    'Plugins without required permissions must be visible.'
);
assertTrue(
    PluginNavigationContract::hasCapabilityAccess(['plugins.manage', 'billing.read'], ['plugins.manage']),
    'Granted permissions should allow plugin navigation.'
);
assertTrue(
    PluginNavigationContract::hasCapabilityAccess(['*'], ['plugins.manage', 'billing.read']),
    'Wildcard permission should grant plugin navigation.'
);
assertTrue(
    !PluginNavigationContract::hasCapabilityAccess(['plugins.manage'], ['plugins.manage', 'billing.read']),
    'Missing one required permission must block plugin navigation.'
);

assertTrue(
    PluginNavigationContract::isVisibleInNavigation('enabled', true),
    'Enabled and active plugin must be visible.'
);
assertTrue(
    !PluginNavigationContract::isVisibleInNavigation('installed', true),
    'Installed plugin should not be visible in navigation.'
);
assertTrue(
    !PluginNavigationContract::isVisibleInNavigation('enabled', false),
    'Disabled plugin should not be visible in navigation.'
);
assertTrue(
    !PluginNavigationContract::isVisibleInNavigation('suspended', true),
    'Suspended plugin should not be visible in navigation.'
);

echo "Plugin navigation contract checks passed\n";
