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
     * Mengirim file/document via WhatsApp menggunakan URL (Fonte API)
     * 
     * Fonte API memerlukan URL publik untuk file, bukan upload langsung
     * 
     * @param string $phoneNumber Nomor tujuan
     * @param string $fileUrl URL publik file yang akan dikirim
     * @param string $filename Nama file (opsional)
     * @param string $caption Caption untuk file (opsional)
     * @return array
     */
    public function sendDocumentByUrl($phoneNumber, $fileUrl, $filename = '', $caption = '')
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            if (empty($this->apiKey)) {
                Log::error('Fonte API Key tidak dikonfigurasi');
                return [
                    'success' => false,
                    'error' => 'Fonte API Key tidak dikonfigurasi'
                ];
            }

            // Fonte API menggunakan parameter 'url' untuk mengirim file sebagai document
            // Endpoint: /send dengan parameter url (akan otomatis dikirim sebagai file attachment)
            // Catatan: URL harus bisa diakses publik (tidak bisa localhost tanpa tunneling)
            $requestData = [
                'target' => $phoneNumber,
                'message' => $caption ?: '📎 File terlampir',
                'url' => $fileUrl
                // Fonte API akan otomatis detect file type dari URL
            ];

            if ($filename) {
                $requestData['filename'] = $filename;
            }

            Log::info('Fonte API Send Document by URL (metode utama untuk Fonte gratis)', [
                'api_url' => $this->baseUrl . '/send',
                'target' => $phoneNumber,
                'file_url' => $fileUrl,
                'filename' => $filename,
                'caption_length' => strlen($caption)
            ]);

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/send', $requestData);

            Log::info('Fonte API Send Document Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $phoneNumber
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['status']) && $responseData['status'] === true) {
                    Log::info('✅ PDF berhasil dikirim via Fonte API menggunakan URL', [
                        'phone' => $phoneNumber,
                        'file_url' => $fileUrl,
                        'filename' => $filename,
                        'response' => $responseData
                    ]);
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'message' => 'File PDF berhasil dikirim sebagai attachment via URL'
                    ];
                } else {
                    $reason = $responseData['reason'] ?? $responseData['message'] ?? 'Unknown error';
                    Log::warning('❌ Fonte API returned status false untuk document URL', [
                        'reason' => $reason,
                        'file_url' => $fileUrl,
                        'response' => $responseData
                    ]);
                    return [
                        'success' => false,
                        'error' => $reason,
                        'details' => $responseData
                    ];
                }
            }

            $errorData = $response->json();
            return [
                'success' => false,
                'error' => $errorData['message'] ?? $errorData['reason'] ?? 'Gagal mengirim file',
                'details' => $errorData
            ];

        } catch (\Exception $e) {
            Log::error('Error sending document via WhatsApp', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
                'file_url' => $fileUrl
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mengirim file/document via WhatsApp menggunakan file lokal (CURLFile)
     * 
     * Menggunakan file path lokal dari storage, bukan URL publik
     * Cocok untuk localhost atau ketika file sudah ada di server
     * 
     * @param string $phoneNumber Nomor tujuan
     * @param string $filePath Path file lokal (relative dari storage/app/public atau absolute path)
     * @param string $filename Nama file (opsional)
     * @param string $caption Caption untuk file (opsional)
     * @return array
     */
    public function sendDocumentByFile($phoneNumber, $filePath, $filename = '', $caption = '')
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            if (empty($this->apiKey)) {
                Log::error('Fonte API Key tidak dikonfigurasi');
                return [
                    'success' => false,
                    'error' => 'Fonte API Key tidak dikonfigurasi'
                ];
            }

            // Resolve file path
            // Jika relative path (misal: pdfs/diagnosis-5.pdf), convert ke absolute path
            $absolutePath = null;
            if (file_exists($filePath)) {
                // Sudah absolute path
                $absolutePath = $filePath;
            } else {
                // Coba dari storage/app/public
                $storagePath = storage_path('app/public/' . ltrim($filePath, '/'));
                if (file_exists($storagePath)) {
                    $absolutePath = $storagePath;
                } else {
                    // Coba dari public/storage (jika storage link sudah dibuat)
                    $publicPath = public_path('storage/' . ltrim($filePath, '/'));
                    if (file_exists($publicPath)) {
                        $absolutePath = $publicPath;
                    }
                }
            }

            if (!$absolutePath || !file_exists($absolutePath)) {
                Log::error('File tidak ditemukan', [
                    'file_path' => $filePath,
                    'tried_paths' => [
                        $filePath,
                        storage_path('app/public/' . ltrim($filePath, '/')),
                        public_path('storage/' . ltrim($filePath, '/'))
                    ]
                ]);
                return [
                    'success' => false,
                    'error' => 'File tidak ditemukan: ' . $filePath
                ];
            }

            // Jika filename kosong, ambil dari path
            if (empty($filename)) {
                $filename = basename($absolutePath);
            }

            // Buat CURLFile untuk upload file
            // Format sesuai dokumentasi Fonte: CURLFile(path) atau CURLFile(path, mime_type, filename)
            $mimeType = mime_content_type($absolutePath) ?: 'application/pdf';
            $curlFile = new \CURLFile($absolutePath, $mimeType, $filename);

            // Prepare request data dengan multipart/form-data
            // Sesuai dokumentasi Fonte API untuk send document:
            // - target: nomor tujuan
            // - message: caption/pesan
            // - file: file attachment (CURLFile)
            // - filename: nama file (opsional, tapi disarankan)
            // Format request untuk Fonte API send document via file upload
            // CATATAN: Fonte gratis mungkin tidak benar-benar support file upload
            // Meskipun API return success, file mungkin tidak terkirim sebagai attachment
            // Solusi: Gunakan URL publik (sendDocumentByUrl) sebagai metode utama
            $requestData = [
                'target' => $phoneNumber,
                'message' => $caption ?: '📎 File terlampir',
                'file' => $curlFile, // CURLFile untuk multipart upload
                'filename' => $filename // Nama file untuk ditampilkan di WhatsApp
            ];
            
            Log::warning('⚠️ Menggunakan file upload - Fonte gratis mungkin tidak support!', [
                'suggestion' => 'Gunakan sendDocumentByUrl dengan URL publik untuk hasil yang lebih reliable'
            ]);

            // Verifikasi file sebelum kirim
            $fileSize = filesize($absolutePath);
            $isReadable = is_readable($absolutePath);
            
            Log::info('Fonte API Send Document by Local File', [
                'url' => $this->baseUrl . '/send',
                'target' => $phoneNumber,
                'file_path' => $absolutePath,
                'filename' => $filename,
                'file_size' => $fileSize,
                'file_exists' => file_exists($absolutePath),
                'is_readable' => $isReadable,
                'mime_type' => $mimeType,
                'curl_file_class' => get_class($curlFile),
                'curl_file_path' => $curlFile->getFilename(),
                'curl_file_mime' => $curlFile->getMimeType(),
                'curl_file_postname' => $curlFile->getPostFilename()
            ]);
            
            if (!$isReadable || $fileSize === false) {
                Log::error('File tidak dapat dibaca', [
                    'file_path' => $absolutePath,
                    'is_readable' => $isReadable,
                    'file_size' => $fileSize
                ]);
                return [
                    'success' => false,
                    'error' => 'File tidak dapat dibaca: ' . $absolutePath
                ];
            }

            // Gunakan curl langsung untuk multipart/form-data
            // PENTING: Jangan set Content-Type header, biarkan curl set otomatis untuk multipart
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestData, // CURLFile akan otomatis membuat multipart/form-data
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $this->apiKey
                    // Jangan set Content-Type, biarkan curl set otomatis untuk multipart/form-data
                ],
                CURLOPT_TIMEOUT => 60, // Timeout lebih lama untuk upload file
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_VERBOSE => false, // Set true untuk debugging
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            // Cleanup curl resource (curl_close deprecated in PHP 8.0+)
            if (is_resource($ch)) {
                curl_close($ch);
            } else {
                unset($ch);
            }

            if ($curlError) {
                Log::error('CURL Error sending document', [
                    'error' => $curlError,
                    'phone' => $phoneNumber
                ]);
                return [
                    'success' => false,
                    'error' => 'CURL Error: ' . $curlError
                ];
            }

            Log::info('Fonte API Send Document by File Response', [
                'status' => $httpCode,
                'body' => $responseBody,
                'phone' => $phoneNumber,
                'response_length' => strlen($responseBody)
            ]);

            $responseData = json_decode($responseBody, true);

            // Log response data untuk debugging
            if ($responseData) {
                Log::info('Fonte API Response Data', [
                    'status' => $responseData['status'] ?? null,
                    'reason' => $responseData['reason'] ?? null,
                    'message' => $responseData['message'] ?? null,
                    'full_response' => $responseData
                ]);
            } else {
                Log::warning('Fonte API Response tidak valid JSON', [
                    'raw_response' => $responseBody
                ]);
            }

            if ($httpCode === 200) {
                if (isset($responseData['status']) && $responseData['status'] === true) {
                    Log::info('PDF berhasil dikirim via Fonte API sebagai attachment', [
                        'phone' => $phoneNumber,
                        'filename' => $filename,
                        'response' => $responseData
                    ]);
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'message' => 'File PDF berhasil dikirim sebagai attachment'
                    ];
                } else {
                    // Status false atau tidak ada status
                    $reason = $responseData['reason'] ?? $responseData['message'] ?? 'Unknown error';
                    Log::warning('Fonte API returned status false untuk document', [
                        'reason' => $reason,
                        'http_code' => $httpCode,
                        'full_response' => $responseData,
                        'raw_response' => substr($responseBody, 0, 500)
                    ]);
                    return [
                        'success' => false,
                        'error' => $reason,
                        'details' => $responseData,
                        'http_code' => $httpCode
                    ];
                }
            } else {
                // HTTP error
                $reason = $responseData['reason'] ?? $responseData['message'] ?? 'HTTP Error: ' . $httpCode;
                Log::error('Fonte API HTTP error saat mengirim document', [
                    'http_code' => $httpCode,
                    'reason' => $reason,
                    'response' => $responseData,
                    'raw_response' => substr($responseBody, 0, 500)
                ]);
                return [
                    'success' => false,
                    'error' => $reason,
                    'details' => $responseData,
                    'http_code' => $httpCode
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error sending document via WhatsApp (local file)', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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



