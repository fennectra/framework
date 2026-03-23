<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;

#[Table('nf525_journal')]
class Nf525Journal extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'entity_id' => 'int',
        'user_id' => 'int',
        'details' => 'json',
    ];
}