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
     * Register (tanpa OTP)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'is_verified' => true,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil!',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }

    /**
     * Login (email + password)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $email = trim($request->email);
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.'
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
     * Reset Password - Step 2: Verifikasi OTP (tanpa ganti password)
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak terdaftar.'
            ], 404);
        }

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

        return response()->json([
            'success' => true,
            'message' => 'OTP valid.'
        ], 200);
    }

    /**
     * Reset Password - Step 3: Verify OTP dan Reset Password
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
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'bio' => $user->bio,
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
