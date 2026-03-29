<?php

namespace App\Http\Controllers;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\EmailOtpService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    protected EmailOtpService $emailOtpService;

    public function __construct(EmailOtpService $emailOtpService)
    {
        $this->emailOtpService = $emailOtpService;
    }

    /**
     * Update profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update name
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('bio')) {
            $bio = trim((string) $request->bio);
            $user->bio = $bio !== '' ? $bio : null;
        }

        // Update photo
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            // Store new photo
            $path = $request->file('photo')->store('photos', 'public');
            $user->photo = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => $user
        ], 200);
    }

    public function requestEmailChangeOtp(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'new_email' => 'required|string|email|max:255|unique:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $newEmail = trim((string) $request->new_email);
        if ($newEmail === $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email baru tidak boleh sama dengan email saat ini.'
            ], 422);
        }

        $otpCode = OtpVerification::generateCode();
        $expiresAt = Carbon::now()->addMinutes(10);

        OtpVerification::updateOrCreate(
            [
                'email' => $newEmail,
                'type' => 'email_change',
                'is_verified' => false
            ],
            [
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt
            ]
        );

        $emailResult = $this->emailOtpService->sendOtp($newEmail, $otpCode, 'email_change');

        if (! $emailResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $emailResult['error'] ?? 'Gagal mengirim OTP ke email baru.',
                'error' => $emailResult['error'] ?? null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP telah dikirim ke email baru Anda. Silakan verifikasi.',
            'new_email' => $newEmail
        ], 200);
    }

    public function verifyEmailChangeOtp(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'new_email' => 'required|string|email|max:255|unique:users,email',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $newEmail = trim((string) $request->new_email);
        if ($newEmail === $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email baru tidak boleh sama dengan email saat ini.'
            ], 422);
        }

        $otp = OtpVerification::where('email', $newEmail)
            ->where('otp_code', $request->otp_code)
            ->where('type', 'email_change')
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid atau sudah kadaluarsa.'
            ], 422);
        }

        $user->email = $newEmail;
        $user->save();

        $otp->update(['is_verified' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil diubah.',
            'data' => $user
        ], 200);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.'
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.'
        ], 200);
    }

    /**
     * Get current user profile
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }

    /**
     * Remove photo
     */
    public function removePhoto(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        // Delete photo from storage
        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        // Remove photo from user record
        $user->photo = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil dihapus',
            'data' => $user
        ], 200);
    }
}
