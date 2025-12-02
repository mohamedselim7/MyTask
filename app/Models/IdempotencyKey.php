<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['key','action','request','response'];
    protected $casts = ['request'=>'array','response'=>'array'];
}
