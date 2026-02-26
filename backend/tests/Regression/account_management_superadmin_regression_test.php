<?php

declare(strict_types=1);

use App\Controllers\AccountManagementController;
use App\Core\Request;

require __DIR__ . '/../../src/Core/bootstrap.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ', got: ' . var_export($actual, true));
    }
}

$controller = new AccountManagementController();
$reflection = new ReflectionClass($controller);
$actorMethod = $reflection->getMethod('actor');
$_SERVER['HTTP_X_TENANT_ID'] = 'superadmin';
$_SERVER['HTTP_X_PERMISSIONS'] = '*';
$request = new Request();

$actor = $actorMethod->invoke($controller, 'superadmin', $request);
if (!is_array($actor)) {
    throw new RuntimeException('Superadmin wildcard request must resolve synthetic actor.');
}

assertSameValue('admin', $actor['account_type'] ?? null, 'Superadmin actor type mismatch.');
assertSameValue(1, $actor['is_active'] ?? null, 'Superadmin actor must be active.');

echo "Account management superadmin regression checks passed\n";
