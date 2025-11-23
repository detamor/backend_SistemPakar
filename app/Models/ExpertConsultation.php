<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertConsultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'diagnosis_id',
        'expert_whatsapp',
        'message',
        'expert_response',
        'status',
        'whatsapp_message_id',
    ];

    /**
     * Relasi dengan user (pengguna yang konsultasi)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    /**
     * Relasi dengan diagnosis
     */
    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }
}

