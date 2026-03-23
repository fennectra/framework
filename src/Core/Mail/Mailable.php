<?php

namespace Fennec\Core\Mail;

/**
 * Base class for typed email templates.
 *
 * Extend this class in app/Mail/ to define email templates with typed variables.
 * The Mailer will load the corresponding template from the database and render it.
 *
 * Usage:
 *   Mailer::send(new AccountActivation('user@example.com', 'John', $url));
 */
abstract class Mailable
{
    /** Template name in the email_templates table */
    public string $templateName = '';

    /** Recipient email */
    public string $to = '';

    /** Locale for template resolution (fallback to 'en') */
    public string $locale = 'fr';

    /**
     * Variables to inject into the template.
     *
     * @return array<string, string>
     */
    abstract public function variables(): array;

    /**
     * Default subject for the seeder (French).
     */
    public static function defaultSubjectFr(): string
    {
        return '';
    }

    /**
     * Default subject for the seeder (English).
     */
    public static function defaultSubjectEn(): string
    {
        return '';
    }

    /**
     * Default body for the seeder (French).
     */
    public static function defaultBodyFr(): string
    {
        return '';
    }

    /**
     * Default body for the seeder (English).
     */
    public static function defaultBodyEn(): string
    {
        return '';
    }
}
