<?php

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailOtpService
{
    /**
     * Mengirim OTP via Email
     *
     * @param string $email Alamat email tujuan
     * @param string $otpCode Kode OTP 6 digit
     * @param string $type Tipe OTP: 'registration' atau 'password_reset'
     * @return array
     */
    public function sendOtp(string $email, string $otpCode, string $type = 'registration'): array
    {
        try {
            Mail::to($email)->send(new OtpMail($otpCode, $type));

            Log::info('OTP email sent successfully', [
                'email' => $email,
                'type' => $type,
            ]);

            return [
                'success' => true,
                'message' => 'OTP berhasil dikirim ke email.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email', [
                'email' => $email,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Gagal mengirim OTP ke email. Silakan coba lagi.',
                'details' => $e->getMessage(),
            ];
        }
    }
}
