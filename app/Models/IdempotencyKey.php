<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'source_type',
    'source_id',
    'response_status',
])]
class IdempotencyKey extends Model
{
    //
}
