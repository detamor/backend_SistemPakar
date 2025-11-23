<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('FONTE_API_KEY');
        // Base URL Fonte: https://api.fonnte.com (bukan api.fonte.com)
        // PENTING: Pastikan .env sudah di-set FONTE_BASE_URL=https://api.fonnte.com
        $this->baseUrl = env('FONTE_BASE_URL', 'https://api.fonnte.com');
        
        // Log untuk debugging
        if (empty($this->baseUrl) || strpos($this->baseUrl, 'api.fonte.com') !== false) {
            Log::warning('Fonte Base URL mungkin salah', [
                'base_url' => $this->baseUrl,
                'env_value' => env('FONTE_BASE_URL')
            ]);
        }
    }

    /**
     * Mengirim pesan WhatsApp
     * 
     * Format nomor: 6281234567890 (tanpa +, tanpa spasi, tanpa dash)
     */
    public function sendMessage($phoneNumber, $message)
    {
        try {
            // Format nomor: pastikan tanpa +, spasi, atau dash
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            // Cek API key
            if (empty($this->apiKey)) {
                Log::error('Fonte API Key tidak dikonfigurasi');
                return [
                    'success' => false,
                    'error' => 'Fonte API Key tidak dikonfigurasi. Pastikan FONTE_API_KEY sudah di-set di .env'
                ];
            }

            // Format API Fonte berdasarkan dokumentasi
            // Endpoint: https://api.fonnte.com/send
            // Header: Authorization: {token} (tanpa Bearer)
            // Body: target dan message
            $requestData = [
                'target' => $phoneNumber, // Fonte menggunakan 'target' bukan 'to'
                'message' => $message
            ];
            
            // Log request untuk debugging
            Log::info('Fonte API Request', [
                'url' => $this->baseUrl . '/send',
                'target' => $phoneNumber,
                'message_preview' => substr($message, 0, 50) . '...',
                'api_key_preview' => substr($this->apiKey, 0, 10) . '...'
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey, // Fonte menggunakan token langsung, bukan Bearer
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/send', $requestData);

            // Log response untuk debugging
            Log::info('Fonte API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $phoneNumber,
                'success' => $response->successful()
            ]);

            // Parse response
            $responseData = $response->json();
            
            // Cek apakah response sukses (status 200 tapi bisa jadi status: false di body)
            if ($response->successful()) {
                // Cek status di response body
                if (isset($responseData['status']) && $responseData['status'] === true) {
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'message' => 'Pesan berhasil dikirim'
                    ];
                } else {
                    // Status false di body - ada error dari Fonte
                    $reason = $responseData['reason'] ?? 'Unknown error';
                    
                    // Handle error khusus
                    if (strpos($reason, 'disconnected device') !== false) {
                        return [
                            'success' => false,
                            'error' => 'Device WhatsApp belum terhubung. Silakan hubungkan device di dashboard Fonte terlebih dahulu.',
                            'details' => $responseData
                        ];
                    }
                    
                    return [
                        'success' => false,
                        'error' => $reason,
                        'details' => $responseData
                    ];
                }
            }

            // Jika HTTP error (bukan 200)
            $errorData = $response->json();
            return [
                'success' => false,
                'error' => $errorData['message'] ?? $errorData['reason'] ?? 'Gagal mengirim pesan',
                'details' => $errorData
            ];

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phoneNumber
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format nomor telepon untuk Fonte API
     * Menerima berbagai format: 
     * - +62 896-0201-5724
     * - 089602015724
     * - 081234567890
     * - 6281234567890
     * - +6281234567890
     * Output: 6281234567890 (tanpa +, tanpa spasi, tanpa dash)
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Hapus semua karakter selain angka (termasuk +, spasi, dash, dll)
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Jika kosong setelah di-clean, return as is
        if (empty($phoneNumber)) {
            return $phoneNumber;
        }
        
        // Jika dimulai dengan 62, langsung return (sudah benar)
        if (substr($phoneNumber, 0, 2) === '62') {
            return $phoneNumber;
        }
        
        // Jika dimulai dengan 0, ganti dengan 62
        if (substr($phoneNumber, 0, 1) === '0') {
            return '62' . substr($phoneNumber, 1);
        }
        
        // Jika panjang 9-12 digit dan tidak dimulai dengan 0 atau 62, 
        // kemungkinan nomor lokal, tambahkan 62
        if (strlen($phoneNumber) >= 9 && strlen($phoneNumber) <= 12) {
            return '62' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    /**
     * Mengirim OTP via WhatsApp
     */
    public function sendOtp($phoneNumber, $otpCode)
    {
        $message = "Kode OTP Anda: *{$otpCode}*\n\n";
        $message .= "Kode ini berlaku selama 10 menit.\n";
        $message .= "Jangan bagikan kode ini kepada siapapun.";

        return $this->sendMessage($phoneNumber, $message);
    }
}



