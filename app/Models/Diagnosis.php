<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Diagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plant_id',
        'disease_id',
        'certainty_value',
        'recommendation',
        'all_possibilities_json',
        'matched_symptoms_count',
        'user_notes',
        'status',
        'pdf_path',
    ];

    protected $casts = [
        'certainty_value' => 'decimal:2',
        'all_possibilities_json' => 'array',
        'matched_symptoms_count' => 'integer',
    ];

    /**
     * Relasi dengan user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi dengan plant
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Relasi dengan disease
     */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    /**
     * Relasi dengan symptoms
     */
    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class, 'diagnosis_symptoms')
            ->withPivot('user_cf')
            ->withTimestamps();
    }

    /**
     * Relasi dengan feedback
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class, 'diagnosis_id');
    }

    /**
     * Relasi dengan expert consultations
     */
    public function expertConsultations(): HasMany
    {
        return $this->hasMany(ExpertConsultation::class);
    }
}



