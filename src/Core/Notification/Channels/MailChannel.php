<?php

namespace Fennec\Core\Notification\Channels;

use Fennec\Core\Env;
use Fennec\Core\Notification\NotificationInterface;

/**
 * Envoie la notification par email.
 *
 * Si MAIL_HOST est défini, utilise SMTP via stream_socket_client.
 * Sinon, utilise la fonction mail() native de PHP.
 *
 * Variables d'environnement :
 *   MAIL_HOST, MAIL_PORT (défaut: 587),
 *   MAIL_USER, MAIL_PASSWORD, MAIL_FROM
 */
class MailChannel
{
    public function send(mixed $notifiable, NotificationInterface $notification): void
    {
        $message = $notification->toMail();
        if ($message === null) {
            return;
        }

        $to = $message->to;
        if (empty($to) && is_object($notifiable) && isset($notifiable->email)) {
            $to = $notifiable->email;
        }
        if (empty($to) && is_array($notifiable)) {
            $to = $notifiable['email'] ?? '';
        }
        if (empty($to)) {
            return;
        }

        $from = $message->from ?: Env::get('MAIL_FROM', 'noreply@localhost');
        $host = Env::get('MAIL_HOST', '');

        if ($host !== '') {
            $this->sendSmtp($to, $from, $message->subject, $message->body);
        } else {
            $this->sendNative($to, $from, $message->subject, $message->body);
        }
    }

    private function sendNative(string $to, string $from, string $subject, string $body): void
    {
        $headers = "From: {$from}\r\nContent-Type: text/html; charset=UTF-8\r\n";
        mail($to, $subject, $body, $headers);
    }

    private function sendSmtp(string $to, string $from, string $subject, string $body): void
    {
        $host = Env::get('MAIL_HOST');
        $port = (int) Env::get('MAIL_PORT', '587');
        $user = Env::get('MAIL_USER', '');
        $pass = Env::get('MAIL_PASSWORD', '');

        $socket = stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            30
        );

        if (!$socket) {
            throw new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        try {
            $this->smtpRead($socket);
            $this->smtpCmd($socket, "EHLO localhost\r\n");
            $this->smtpCmd($socket, "STARTTLS\r\n");

            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

            $this->smtpCmd($socket, "EHLO localhost\r\n");

            if ($user !== '') {
                $this->smtpCmd($socket, "AUTH LOGIN\r\n");
                $this->smtpCmd($socket, base64_encode($user) . "\r\n");
                $this->smtpCmd($socket, base64_encode($pass) . "\r\n");
            }

            $this->smtpCmd($socket, "MAIL FROM:<{$from}>\r\n");
            $this->smtpCmd($socket, "RCPT TO:<{$to}>\r\n");
            $this->smtpCmd($socket, "DATA\r\n");

            $headers = "From: {$from}\r\n"
                . "To: {$to}\r\n"
                . "Subject: {$subject}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "\r\n";

            fwrite($socket, $headers . $body . "\r\n.\r\n");
            $this->smtpRead($socket);

            $this->smtpCmd($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    private function smtpCmd($socket, string $cmd): string
    {
        fwrite($socket, $cmd);

        return $this->smtpRead($socket);
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }
}
