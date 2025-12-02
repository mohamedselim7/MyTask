<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IdempotencyKey extends Model
{

    protected $fillable = ['key','action','request','response'];
    protected $casts = ['request'=>'array','response'=>'array'];
}
