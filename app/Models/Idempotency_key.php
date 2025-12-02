<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Idempotency_key extends Model
{
    protected $fillable = ['key','action','request','response'];
    protected $casts = ['request'=>'array','response'=>'array'];
}
