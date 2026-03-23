<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('organizations')]
class Organization extends Model
{
    /**
     * Get all members of this organization.
     */
    public function members(): array
    {
        return OrganizationMember::where('organization_id', '=', $this->id)->get();
    }

    /**
     * Get the owner of this organization.
     */
    public function owner(): ?Model
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => $this->owner_id]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? User::hydrate($row) : null;
    }

    /**
     * Find an organization by its slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM organizations WHERE slug = :slug LIMIT 1',
            ['slug' => $slug]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }

    /**
     * Generate a unique slug from a name.
     */
    public static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while (static::findBySlug($slug) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get organizations for a given user (as owner or member).
     */
    public static function forUser(int $userId): array
    {
        $stmt = DB::raw(
            'SELECT o.* FROM organizations o '
            . 'INNER JOIN organization_members om ON om.organization_id = o.id '
            . 'WHERE om.user_id = :user_id '
            . 'ORDER BY o.created_at DESC',
            ['user_id' => $userId]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn ($row) => static::hydrate($row), $rows);
    }
}