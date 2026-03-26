<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'scientific_name',
        'description',
        'image',
        'care_guide',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relasi dengan diseases
     */
    public function diseases(): HasMany
    {
        return $this->hasMany(Disease::class);
    }

    /**
     * Gejala yang ditandai untuk tanaman ini (nama ruang kerja admin).
     */
    public function symptoms(): HasMany
    {
        return $this->hasMany(Symptom::class);
    }

    /**
     * Relasi dengan diagnoses
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }
}



