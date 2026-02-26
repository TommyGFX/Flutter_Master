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

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

PluginContract::assertValidMetadata(
    'billing_core',
    '1.2.3',
    ['documents', 'pdf'],
    ['plugins.manage']
);

PluginContract::assertValidMetadata(
    'tax_compliance_de',
    '2.0.0-beta.1',
    ['compliance', 'einvoice'],
    ['plugins.manage', 'billing.read']
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
assertThrows(
    static fn () => PluginContract::assertValidMetadata('billing core', '1.0.0', ['documents'], ['plugins.manage']),
    'invalid_plugin_key'
);
assertThrows(
    static fn () => PluginContract::assertValidMetadata('billing_core', '1.0', ['documents'], ['plugins.manage']),
    'invalid_plugin_version'
);
assertThrows(
    static fn () => PluginContract::assertValidMetadata('billing_core', '1.0.0', ['documents', '   '], ['plugins.manage']),
    'invalid_capabilities'
);

assertSame(
    ['before_validate', 'before_finalize', 'after_finalize', 'before_send', 'after_payment'],
    PluginContract::ALLOWED_HOOKS,
    'Allowed hooks list changed unexpectedly.'
);
assertTrue(PluginContract::isAllowedHook('before_validate'), 'before_validate should be allowlisted');
assertTrue(PluginContract::isAllowedHook('after_payment'), 'after_payment should be allowlisted');
assertTrue(!PluginContract::isAllowedHook('after_refund'), 'after_refund should not be allowlisted');
assertTrue(!PluginContract::isAllowedHook('before_send '), 'Hook matching should be strict');

foreach (PluginContract::LIFECYCLE_STATES as $state) {
    PluginContract::assertLifecycleState($state);
}

PluginContract::assertLifecycleTransition('installed', 'enabled');
PluginContract::assertLifecycleTransition('enabled', 'suspended');
PluginContract::assertLifecycleTransition('suspended', 'enabled');
PluginContract::assertLifecycleTransition('suspended', 'retired');
PluginContract::assertLifecycleTransition('retired', 'retired');
PluginContract::assertLifecycleTransition('installed', 'installed');
PluginContract::assertLifecycleTransition('enabled', 'enabled');

assertThrows(static fn () => PluginContract::assertLifecycleTransition('enabled', 'installed'), 'invalid_lifecycle_transition');
assertThrows(static fn () => PluginContract::assertLifecycleTransition('installed', 'retired'), 'invalid_lifecycle_transition');

assertThrows(static fn () => PluginContract::assertLifecycleTransition('installed', 'suspended'), 'invalid_lifecycle_transition');
assertThrows(static fn () => PluginContract::assertLifecycleTransition('retired', 'enabled'), 'invalid_lifecycle_transition');
assertThrows(static fn () => PluginContract::assertLifecycleTransition('draft', 'enabled'), 'invalid_lifecycle_status');

echo "Plugin contract checks passed\n";
