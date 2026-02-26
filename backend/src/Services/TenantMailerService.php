<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class TenantMailerService
{
    public function send(string $to, string $subject, string $html, array $smtpConfig): void
    {
        $transport = new EsmtpTransport($smtpConfig['host'], (int) $smtpConfig['port']);

        if (($smtpConfig['username'] ?? '') !== '') {
            $transport->setUsername((string) $smtpConfig['username']);
            $transport->setPassword((string) ($smtpConfig['password'] ?? ''));
        }

        $encryption = strtolower((string) ($smtpConfig['encryption'] ?? 'tls'));
        if ($encryption === 'ssl') {
            $transport->setTls(false);
            $transport->setStreamOptions(['ssl' => ['crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT]]);
        }

        if ($encryption === 'none') {
            $transport->setTls(false);
        }

        $mailer = new Mailer($transport);

        $email = (new Email())
            ->from(new Address((string) $smtpConfig['from_email'], (string) $smtpConfig['from_name']))
            ->to($to)
            ->subject($subject)
            ->html($html);

        $mailer->send($email);
    }
}
