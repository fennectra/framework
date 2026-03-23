<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;

#[Table('nf525_closings')]
class Nf525Closing extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'total_ht' => 'float',
        'total_tva' => 'float',
        'total_ttc' => 'float',
        'cumulative_total' => 'float',
        'document_count' => 'int',
    ];
}