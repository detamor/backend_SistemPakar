<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'otp_code',
        'type',
        'is_verified',
        'expires_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Cek apakah OTP masih valid
     */
    public function isValid(): bool
    {
        return !$this->is_verified && $this->expires_at->isFuture();
    }

    /**
     * Generate OTP code
     */
    public static function generateCode(): string
    {
        return str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Scope untuk OTP yang masih valid
     */
    public function scopeValid($query)
    {
        return $query->where('is_verified', false)
            ->where('expires_at', '>', now());
    }
}



