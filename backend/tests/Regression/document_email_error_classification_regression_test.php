<?php

declare(strict_types=1);

use App\Controllers\DocumentController;

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

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$rejection = new RuntimeException('Expected response code "250" but got code "554", with message "554 5.7.1 Recipient address rejected".');
$classified = DocumentController::classifyMailTransportFailure($rejection);
assertSameValue(422, $classified['status'] ?? null, 'SMTP 5xx rejection must be mapped to validation-style failure status.');
assertSameValue('email_rejected', $classified['body']['error'] ?? null, 'SMTP 5xx rejection must expose email_rejected error key.');
assertSameValue(554, $classified['body']['smtp_code'] ?? null, 'SMTP code must be preserved for diagnostics.');

$generic = new RuntimeException('Connection timeout while contacting SMTP server');
$genericClassified = DocumentController::classifyMailTransportFailure($generic);
assertSameValue(500, $genericClassified['status'] ?? null, 'Generic SMTP failures should remain internal server errors.');
assertSameValue('email_send_failed', $genericClassified['body']['error'] ?? null, 'Generic SMTP failures should keep email_send_failed error key.');

echo "Document email error classification regression checks passed\n";
