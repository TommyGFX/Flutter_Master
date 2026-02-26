<?php

declare(strict_types=1);

use App\Plugin\PluginContract;

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

function assertThrows(callable $fn, string $expectedMessage): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $exception) {
        assertTrue($exception->getMessage() === $expectedMessage, 'Unexpected exception message: ' . $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException with message: ' . $expectedMessage);
}

PluginContract::assertValidMetadata(
    'billing_core',
    '1.2.3',
    ['documents', 'pdf'],
    ['plugins.manage']
);

assertThrows(
    static fn () => PluginContract::assertValidMetadata('Billing Core', '1.0.0', ['documents'], ['plugins.manage']),
    'invalid_plugin_key'
);
assertThrows(
    static fn () => PluginContract::assertValidMetadata('billing_core', 'v1', ['documents'], ['plugins.manage']),
    'invalid_plugin_version'
);
assertThrows(
    static fn () => PluginContract::assertValidMetadata('billing_core', '1.0.0', [''], ['plugins.manage']),
    'invalid_capabilities'
);
assertThrows(
    static fn () => PluginContract::assertValidMetadata('billing_core', '1.0.0', ['documents'], ['']),
    'invalid_required_permissions'
);

assertTrue(PluginContract::isAllowedHook('before_validate'), 'before_validate should be allowlisted');
assertTrue(!PluginContract::isAllowedHook('after_refund'), 'after_refund should not be allowlisted');

PluginContract::assertLifecycleTransition('installed', 'enabled');
PluginContract::assertLifecycleTransition('enabled', 'suspended');
PluginContract::assertLifecycleTransition('suspended', 'enabled');
PluginContract::assertLifecycleTransition('suspended', 'retired');
PluginContract::assertLifecycleTransition('retired', 'retired');

assertThrows(static fn () => PluginContract::assertLifecycleTransition('installed', 'suspended'), 'invalid_lifecycle_transition');
assertThrows(static fn () => PluginContract::assertLifecycleTransition('retired', 'enabled'), 'invalid_lifecycle_transition');
assertThrows(static fn () => PluginContract::assertLifecycleTransition('draft', 'enabled'), 'invalid_lifecycle_status');

echo "Plugin contract checks passed\n";
