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
        $encryption = strtolower((string) ($smtpConfig['encryption'] ?? 'tls'));
        $useSslTransport = $encryption === 'ssl';

        $transport = new EsmtpTransport($smtpConfig['host'], (int) $smtpConfig['port'], $useSslTransport);

        if (($smtpConfig['username'] ?? '') !== '') {
            $transport->setUsername((string) $smtpConfig['username']);
            $transport->setPassword((string) ($smtpConfig['password'] ?? ''));
        }

        if ($encryption === 'none') {
            $transport->setAutoTls(false);
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
