<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Symptom extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'code',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Tanaman induk untuk konteks admin / diagnosis (tetap ada walau pivot penyakit kosong).
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Relasi dengan diseases melalui pivot table
     */
    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class, 'disease_symptoms')
            ->withPivot('certainty_factor')
            ->withTimestamps();
    }

    /**
     * Relasi dengan diagnoses
     */
    public function diagnoses(): BelongsToMany
    {
        return $this->belongsToMany(Diagnosis::class, 'diagnosis_symptoms')
            ->withPivot('user_cf')
            ->withTimestamps();
    }
}



