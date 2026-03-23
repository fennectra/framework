<?php

namespace Fennec\Core\Mail;

use Fennec\Core\Notification\Channels\MailChannel;
use Fennec\Core\Notification\Messages\MailMessage;
use Fennec\Core\Notification\Notification;

/**
 * Send emails via database templates or Mailable classes.
 *
 * Usage with Mailable:
 *   Mailer::sendMailable(new AccountActivation('user@example.com', 'John', $url));
 *
 * Usage with template name:
 *   Mailer::sendTemplate('user@example.com', 'account_activation', ['name' => 'John']);
 */
class Mailer
{
    /**
     * Send a Mailable (typed email template class).
     */
    public static function sendMailable(Mailable $mail): void
    {
        self::sendTemplate($mail->to, $mail->templateName, $mail->variables(), $mail->locale);
    }

    /**
     * Send an email based on a database template.
     *
     * @param string $to Recipient email address
     * @param string $templateName Template name (e.g., 'account_activation')
     * @param array<string, string> $variables Variables to substitute in the template
     * @param string $locale Template locale (fallback to 'en')
     */
    public static function sendTemplate(
        string $to,
        string $templateName,
        array $variables = [],
        string $locale = 'fr',
    ): void {
        // Cherche la classe EmailTemplate dans l'app
        $modelClass = 'App\\Models\\EmailTemplate';

        if (!class_exists($modelClass)) {
            throw new \RuntimeException(
                "EmailTemplate model not found. Run './forge make:email' first."
            );
        }

        $template = $modelClass::findByNameAndLocale($templateName, $locale);

        if (!$template) {
            throw new \RuntimeException(
                "Email template '{$templateName}' not found for locale '{$locale}'."
            );
        }

        $subject = $template->renderSubject($variables);
        $body = $template->render($variables);

        self::send($to, $subject, $body);
    }

    /**
     * Envoie un email brut (sans template DB).
     */
    public static function send(string $to, string $subject, string $body): void
    {
        $notification = new class ($to, $subject, $body) extends Notification {
            public function __construct(
                private readonly string $to,
                private readonly string $subject,
                private readonly string $body,
            ) {
            }

            public function via(): array
            {
                return ['mail'];
            }

            public function toMail(): MailMessage
            {
                $msg = new MailMessage();
                $msg->to = $this->to;
                $msg->subject = $this->subject;
                $msg->body = $this->body;

                return $msg;
            }
        };

        $channel = new MailChannel();
        $channel->send(null, $notification);
    }
}
