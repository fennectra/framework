<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;
use Fennec\Core\Relations\HasMany;

#[Table('consent_objects')]
class ConsentObject extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'object_version' => 'int',
        'object_previous_version' => 'int',
        'is_required' => 'bool',
    ];

    /**
     * Les consentements utilisateurs lies a ce document.
     */
    public function userConsents(): HasMany
    {
        return $this->hasMany(UserConsent::class, 'consent_object_id');
    }

    /**
     * Retourne la derniere version active pour une cle donnee.
     */
    public static function latestByKey(string $key): ?self
    {
        $results = static::where('key', '=', $key)
            ->orderBy('object_version', 'DESC')
            ->limit(1)
            ->get();

        return $results[0] ?? null;
    }

    /**
     * Retourne toutes les cles distinctes avec leur derniere version.
     */
    public static function allLatest(): array
    {
        $all = static::query()->orderBy('key')->orderBy('object_version', 'DESC')->get();
        $latest = [];
        foreach ($all as $item) {
            $key = $item->getAttribute('key');
            if (!isset($latest[$key])) {
                $latest[$key] = $item;
            }
        }

        return array_values($latest);
    }

    /**
     * Cree une nouvelle version d'un document existant.
     */
    public static function createNewVersion(string $key, string $name, string $content, bool $isRequired = true): self
    {
        $current = static::latestByKey($key);
        $newVersion = $current ? $current->getAttribute('object_version') + 1 : 1;
        $previousId = $current ? $current->getAttribute('id') : null;

        return static::create([
            'object_name' => $name,
            'object_content' => $content,
            'object_version' => $newVersion,
            'object_previous_version' => $previousId,
            'key' => $key,
            'is_required' => $isRequired,
        ]);
    }
}