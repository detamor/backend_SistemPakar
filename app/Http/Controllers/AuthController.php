<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpVerification;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected $emailOtpService;

    public function __construct(EmailOtpService $emailOtpService)
    {
        $this->emailOtpService = $emailOtpService;
    }

    /**
     * Register - Step 1: Request OTP via Email
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'whatsapp_number' => 'nullable|string',
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
                'email' => $request->email,
                'type' => 'registration',
                'is_verified' => false
            ],
            [
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt
            ]
        );

        // Kirim OTP via Email
        $emailResult = $this->emailOtpService->sendOtp(
            $request->email,
            $otpCode,
            'registration'
        );

        if (!$emailResult['success']) {
            Log::error('Failed to send OTP email', [
                'email' => $request->email,
                'error' => $emailResult['error'] ?? 'Unknown error',
                'details' => $emailResult['details'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $emailResult['error'] ?? 'Gagal mengirim OTP ke email.',
                'error' => $emailResult['error'] ?? null
            ], 500);
        }

        // Simpan data sementara di cache dengan key berdasarkan email
        $tempData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'whatsapp_number' => $request->whatsapp_number ?? null,
        ];

        cache()->put("register_temp_{$request->email}", $tempData, 600); // 10 menit

        return response()->json([
            'success' => true,
            'message' => 'OTP telah dikirim ke email Anda. Silakan verifikasi.',
            'email' => $request->email
        ], 200);
    }

    /**
     * Register - Step 2: Verify OTP dan Create User
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
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

            // Cari OTP yang valid berdasarkan email
            $otp = OtpVerification::where('email', $request->email)
                ->where('otp_code', $request->otp_code)
                ->where('type', 'registration')
                ->where('is_verified', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$otp) {
                Log::warning('OTP verification failed', [
                    'email' => $request->email,
                    'otp_code' => $request->otp_code,
                ]);
                
                $response = response()->json([
                    'success' => false,
                    'message' => 'OTP tidak valid atau sudah kadaluarsa.'
                ], 422);
                $this->addCorsHeaders($response);
                return $response;
            }

            // Ambil data temporary dari cache
            $cacheKey = "register_temp_{$request->email}";
            $tempData = cache()->get($cacheKey);

            if (!$tempData) {
                Log::error('Register temp data not found', [
                    'email' => $request->email,
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
                    'whatsapp_number' => $tempData['whatsapp_number'] ?? null,
                    'role' => 'user',
                    'is_verified' => true,
                ]);

                // Mark OTP as verified
                $otp->update(['is_verified' => true]);

                // Hapus temporary data
                cache()->forget($cacheKey);
            } catch (\Exception $e) {
                Log::error('Error creating user during OTP verification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $request->email
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
                'email' => $request->email ?? null
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
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = trim($request->email);
        
        // Cek apakah input adalah email atau nomor WhatsApp
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            // Login dengan email
            $user = User::where('email', $identifier)->first();
        } else {
            // Login dengan nomor WhatsApp
            $digitsOnly = preg_replace('/[^0-9]/', '', $identifier);
            
            // Normalisasi ke format standar (62xxxxxxxxxxx)
            $normalized = $digitsOnly;
            if (strpos($normalized, '0') === 0) {
                $normalized = '62' . substr($normalized, 1);
            } elseif (strpos($normalized, '62') !== 0) {
                $normalized = '62' . $normalized;
            }
            
            // Cari user dengan berbagai variasi format nomor WhatsApp
            $user = User::where(function($query) use ($identifier, $digitsOnly, $normalized) {
                $query->where('whatsapp_number', $identifier)
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(whatsapp_number, '+', ''), '-', ''), ' ', '') = ?", [$digitsOnly])
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
     * Reset Password - Step 1: Request OTP via Email
     */
    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user dengan email tersebut ada
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak terdaftar.'
            ], 404);
        }

        // Generate OTP
        $otpCode = OtpVerification::generateCode();
        $expiresAt = Carbon::now()->addMinutes(10);

        // Simpan atau update OTP
        OtpVerification::updateOrCreate(
            [
                'email' => $request->email,
                'type' => 'password_reset',
                'is_verified' => false
            ],
            [
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt
            ]
        );

        // Kirim OTP via Email
        $emailResult = $this->emailOtpService->sendOtp(
            $request->email,
            $otpCode,
            'password_reset'
        );

        if (!$emailResult['success']) {
            Log::error('Failed to send reset password OTP email', [
                'email' => $request->email,
                'error' => $emailResult['error'] ?? 'Unknown error'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP ke email. Silakan coba lagi.',
                'error' => $emailResult['error'] ?? null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP telah dikirim ke email Anda. Silakan verifikasi.',
            'email' => $request->email
        ], 200);
    }

    /**
     * Reset Password - Step 2: Verify OTP dan Reset Password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
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
        $otp = OtpVerification::where('email', $request->email)
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
        $user = User::where('email', $request->email)->first();

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
            
            // Return user data
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
