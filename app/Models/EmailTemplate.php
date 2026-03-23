<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('email_templates')]
class EmailTemplate extends Model
{
    /**
     * Remplace les variables {{key}} dans le body du template.
     */
    public function render(array $vars): string
    {
        $body = $this->body;

        foreach ($vars as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    /**
     * Remplace les variables {{key}} dans le subject du template.
     */
    public function renderSubject(array $vars): string
    {
        $subject = $this->subject;

        foreach ($vars as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
        }

        return $subject;
    }

    /**
     * Recherche un template par nom et locale, avec fallback sur 'en'.
     */
    public static function findByNameAndLocale(string $name, string $locale): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM email_templates WHERE name = :name AND locale = :locale LIMIT 1',
            ['name' => $name, 'locale' => $locale]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return static::hydrate($row);
        }

        // Fallback to 'en'
        if ($locale !== 'en') {
            $stmt = DB::raw(
                'SELECT * FROM email_templates WHERE name = :name AND locale = :locale LIMIT 1',
                ['name' => $name, 'locale' => 'en']
            );

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                return static::hydrate($row);
            }
        }

        return null;
    }
}