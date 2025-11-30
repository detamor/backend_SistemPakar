<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertaintyFactorLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'value',
        'order',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:1',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];
}

