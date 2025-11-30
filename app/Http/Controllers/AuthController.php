<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpVerification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Register - Step 1: Request OTP
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            // Menerima format: +62 896-0201-5724, 081234567890, 6281234567890, dll
            'whatsapp_number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Hapus semua karakter non-digit untuk validasi
                    $digitsOnly = preg_replace('/[^0-9]/', '', $value);
                    
                    // Validasi panjang (min 10, max 15 digit setelah format)
                    if (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 15) {
                        $fail('Nomor WhatsApp harus antara 10-15 digit.');
                    }
                    
                    // Validasi harus dimulai dengan 0, 62, atau +62
                    $cleanValue = preg_replace('/[\s\-]/', '', $value);
                    if (!preg_match('/^(\+62|62|0)/', $cleanValue)) {
                        $fail('Nomor WhatsApp harus dimulai dengan +62, 62, atau 0.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate OTP
        $otpCode = OtpVerification::generateCode();
        $expiresAt = Carbon::now()->addMinutes(10);

        // Simpan atau update OTP
        OtpVerification::updateOrCreate(
            [
                'whatsapp_number' => $request->whatsapp_number,
                'type' => 'registration',
                'is_verified' => false
            ],
            [
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt
            ]
        );

        // Kirim OTP via WhatsApp
        $whatsappResult = $this->whatsappService->sendOtp(
            $request->whatsapp_number,
            $otpCode
        );

        if (!$whatsappResult['success']) {
            Log::error('Failed to send OTP', [
                'whatsapp_number' => $request->whatsapp_number,
                'error' => $whatsappResult['error'],
                'details' => $whatsappResult['details'] ?? null
            ]);

            // Tampilkan error yang lebih informatif
            $errorMessage = $whatsappResult['error'] ?? 'Gagal mengirim OTP.';
            
            // Jika device disconnected, beri pesan yang jelas
            if (strpos($errorMessage, 'disconnected device') !== false || 
                strpos($errorMessage, 'belum terhubung') !== false) {
                $errorMessage = 'Device WhatsApp belum terhubung. Silakan hubungkan device di dashboard Fonte terlebih dahulu.';
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => $whatsappResult['error'],
                'details' => $whatsappResult['details'] ?? null
            ], 500);
        }

        // Simpan data sementara di session (atau bisa pakai cache/redis)
        // Untuk production, gunakan cache atau database temporary
        $tempData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'whatsapp_number' => $request->whatsapp_number,
        ];

        // Simpan ke cache dengan key berdasarkan whatsapp_number
        cache()->put("register_temp_{$request->whatsapp_number}", $tempData, 600); // 10 menit

        return response()->json([
            'success' => true,
            'message' => 'OTP telah dikirim ke WhatsApp Anda. Silakan verifikasi.',
            'whatsapp_number' => $request->whatsapp_number
        ], 200);
    }

    /**
     * Register - Step 2: Verify OTP dan Create User
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'whatsapp_number' => 'required|string',
                'otp_code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                $response = response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
                $this->addCorsHeaders($response);
                return $response;
            }

            // Format nomor untuk pencarian - harus sama dengan format saat register
            // Saat register, nomor disimpan dalam format asli (dari request)
            // Jadi kita cari dengan format yang sama
            
            // Cari OTP yang valid - coba beberapa format
            $searchNumbers = [
                $request->whatsapp_number, // Format asli
                preg_replace('/[\s\-]/', '', $request->whatsapp_number), // Hapus spasi dan dash
                preg_replace('/[^0-9]/', '', $request->whatsapp_number), // Hanya angka
            ];
            
            // Jika dimulai dengan +62 atau 62, tambahkan variasi tanpa prefix
            $cleanNumber = preg_replace('/[^0-9]/', '', $request->whatsapp_number);
            if (substr($cleanNumber, 0, 2) === '62') {
                $searchNumbers[] = '0' . substr($cleanNumber, 2); // Format dengan 0
            }
            if (substr($cleanNumber, 0, 1) === '0') {
                $searchNumbers[] = '62' . substr($cleanNumber, 1); // Format dengan 62
            }
            
            // Hapus duplikat
            $searchNumbers = array_unique($searchNumbers);
            
            // Cari OTP dengan berbagai format nomor
            $otp = OtpVerification::whereIn('whatsapp_number', $searchNumbers)
                ->where('otp_code', $request->otp_code)
                ->where('type', 'registration')
                ->where('is_verified', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$otp) {
                Log::warning('OTP verification failed', [
                    'whatsapp_number' => $request->whatsapp_number,
                    'otp_code' => $request->otp_code,
                    'searched_numbers' => $searchNumbers
                ]);
                
                $response = response()->json([
                    'success' => false,
                    'message' => 'OTP tidak valid atau sudah kadaluarsa.'
                ], 422);
                $this->addCorsHeaders($response);
                return $response;
            }

            // Ambil data temporary dari cache - coba dengan berbagai format
            $tempData = null;
            $foundCacheKey = null;
            foreach ($searchNumbers as $searchNumber) {
                $cacheKey = "register_temp_{$searchNumber}";
                $tempData = cache()->get($cacheKey);
                if ($tempData) {
                    $foundCacheKey = $cacheKey; // Simpan key yang ditemukan
                    break; // Jika ketemu, stop loop
                }
            }

            if (!$tempData) {
                Log::error('Register temp data not found', [
                    'whatsapp_number' => $request->whatsapp_number,
                    'searched_numbers' => $searchNumbers,
                    'otp_found' => $otp ? true : false,
                    'otp_whatsapp' => $otp ? $otp->whatsapp_number : null
                ]);
                
                $response = response()->json([
                    'success' => false,
                    'message' => 'Data registrasi tidak ditemukan. Silakan daftar ulang.'
                ], 422);
                
                $this->addCorsHeaders($response);
                return $response;
            }

            try {
                // Buat user
                $user = User::create([
                    'name' => $tempData['name'],
                    'email' => $tempData['email'],
                    'password' => $tempData['password'],
                    'whatsapp_number' => $tempData['whatsapp_number'],
                    'role' => 'user',
                    'is_verified' => true,
                ]);

                // Mark OTP as verified
                $otp->update(['is_verified' => true]);

                // Hapus temporary data menggunakan key yang ditemukan
                if ($foundCacheKey) {
                    cache()->forget($foundCacheKey);
                } else {
                    // Fallback: hapus dengan semua kemungkinan format
                    foreach ($searchNumbers as $searchNumber) {
                        cache()->forget("register_temp_{$searchNumber}");
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error creating user during OTP verification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'whatsapp_number' => $request->whatsapp_number
                ]);
                
                $response = response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat akun. Silakan coba lagi atau hubungi admin.'
                ], 500);
                
                $this->addCorsHeaders($response);
                return $response;
            }

            // Generate token
            $token = $user->createToken('api-token')->plainTextToken;

            $response = response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil!',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
            
            $this->addCorsHeaders($response);
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Unexpected error in verifyOtp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'whatsapp_number' => $request->whatsapp_number ?? null
            ]);
            
            $response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat verifikasi OTP. Silakan coba lagi.'
            ], 500);
            
            $this->addCorsHeaders($response);
            return $response;
        }
    }
    
    /**
     * Helper method untuk menambahkan CORS headers ke response
     */
    protected function addCorsHeaders($response)
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }

    /**
     * Login - Support email atau nomor WhatsApp
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string', // Changed from 'email' to 'string' to accept both email and phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = trim($request->email); // Bisa email atau nomor WhatsApp
        
        // Cek apakah input adalah email atau nomor WhatsApp
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            // Login dengan email
            $user = User::where('email', $identifier)->first();
        } else {
            // Login dengan nomor WhatsApp
            // Normalisasi nomor WhatsApp (hapus karakter non-digit)
            $digitsOnly = preg_replace('/[^0-9]/', '', $identifier);
            
            // Normalisasi ke format standar (62xxxxxxxxxxx)
            $normalized = $digitsOnly;
            if (strpos($normalized, '0') === 0) {
                // Jika dimulai dengan 0, ganti dengan 62
                $normalized = '62' . substr($normalized, 1);
            } elseif (strpos($normalized, '62') !== 0) {
                // Jika tidak dimulai dengan 62 atau 0, tambahkan 62
                $normalized = '62' . $normalized;
            }
            
            // Cari user dengan berbagai variasi format nomor WhatsApp
            $user = User::where(function($query) use ($identifier, $digitsOnly, $normalized) {
                // Exact match dengan format asli
                $query->where('whatsapp_number', $identifier)
                      // Match dengan digits only
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(whatsapp_number, '+', ''), '-', ''), ' ', '') = ?", [$digitsOnly])
                      // Match dengan format normalisasi
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(whatsapp_number, '+', ''), '-', ''), ' ', '') = ?", [$normalized]);
            })->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/nomor WhatsApp atau password salah.'
            ], 401);
        }

        // Generate token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil!',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 200);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil!'
        ], 200);
    }

    /**
     * Reset Password - Step 1: Request OTP
     */
    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Menerima format: +62 896-0201-5724, 081234567890, 6281234567890, dll
            'whatsapp_number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Hapus semua karakter non-digit untuk validasi
                    $digitsOnly = preg_replace('/[^0-9]/', '', $value);
                    
                    // Validasi panjang (min 10, max 15 digit setelah format)
                    if (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 15) {
                        $fail('Nomor WhatsApp harus antara 10-15 digit.');
                    }
                    
                    // Validasi harus dimulai dengan 0, 62, atau +62
                    $cleanValue = preg_replace('/[\s\-]/', '', $value);
                    if (!preg_match('/^(\+62|62|0)/', $cleanValue)) {
                        $fail('Nomor WhatsApp harus dimulai dengan +62, 62, atau 0.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user dengan nomor WhatsApp tersebut ada
        $user = User::where('whatsapp_number', $request->whatsapp_number)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp tidak terdaftar.'
            ], 404);
        }

        // Generate OTP
        $otpCode = OtpVerification::generateCode();
        $expiresAt = Carbon::now()->addMinutes(10);

        // Simpan atau update OTP
        OtpVerification::updateOrCreate(
            [
                'whatsapp_number' => $request->whatsapp_number,
                'type' => 'password_reset',
                'is_verified' => false
            ],
            [
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt
            ]
        );

        // Kirim OTP via WhatsApp
        $whatsappResult = $this->whatsappService->sendOtp(
            $request->whatsapp_number,
            $otpCode
        );

        if (!$whatsappResult['success']) {
            Log::error('Failed to send reset password OTP', [
                'whatsapp_number' => $request->whatsapp_number,
                'error' => $whatsappResult['error']
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP. Pastikan nomor WhatsApp valid.',
                'error' => $whatsappResult['error']
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP telah dikirim ke WhatsApp Anda. Silakan verifikasi.',
            'whatsapp_number' => $request->whatsapp_number
        ], 200);
    }

    /**
     * Reset Password - Step 2: Verify OTP dan Reset Password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_number' => 'required|string',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Cari OTP yang valid
        $otp = OtpVerification::where('whatsapp_number', $request->whatsapp_number)
            ->where('otp_code', $request->otp_code)
            ->where('type', 'password_reset')
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid atau sudah kadaluarsa.'
            ], 422);
        }

        // Cari user
        $user = User::where('whatsapp_number', $request->whatsapp_number)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Mark OTP as verified
        $otp->update(['is_verified' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Silakan login dengan password baru.'
        ], 200);
    }

    /**
     * Get Current User
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }
            
            // Return user data tanpa relasi untuk menghindari error
        return response()->json([
            'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'whatsapp_number' => $user->whatsapp_number,
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'is_verified' => $user->is_verified,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
        ], 200);
        } catch (\Exception $e) {
            Log::error('Error in AuthController::me', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


