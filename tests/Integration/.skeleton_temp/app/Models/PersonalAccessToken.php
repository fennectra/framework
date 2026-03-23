<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('personal_access_tokens')]
class PersonalAccessToken extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'user_id' => 'int',
    ];

    /**
     * Get the user who owns this token.
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Find a token by its plain-text value.
     */
    public static function findByToken(string $token): ?static
    {
        $hashed = hash('sha256', $token);

        $stmt = DB::raw(
            'SELECT * FROM personal_access_tokens WHERE token = :token LIMIT 1',
            ['token' => $hashed]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}