<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Diagnosis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    /**
     * Submit feedback untuk diagnosis
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'diagnosis_id' => 'required|exists:diagnoses,id',
                'accuracy' => 'required|in:accurate,somewhat_accurate,inaccurate',
                'comment' => 'nullable|string|max:1000',
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
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Cek apakah diagnosis milik user
            $diagnosis = Diagnosis::where('id', $request->diagnosis_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$diagnosis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Diagnosis tidak ditemukan atau bukan milik Anda'
                ], 404);
            }

            // Cek apakah sudah ada feedback untuk diagnosis ini
            $existingFeedback = Feedback::where('diagnosis_id', $request->diagnosis_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingFeedback) {
                // Update feedback yang sudah ada
                $existingFeedback->update([
                    'accuracy' => $request->accuracy,
                    'comment' => $request->comment,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Feedback berhasil diperbarui',
                    'data' => $existingFeedback
                ], 200);
            }

            // Buat feedback baru
            $feedback = Feedback::create([
                'user_id' => $user->id,
                'diagnosis_id' => $request->diagnosis_id,
                'accuracy' => $request->accuracy,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Terima kasih atas feedback Anda',
                'data' => $feedback
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error storing feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan feedback',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get feedback untuk diagnosis tertentu
     */
    public function show(Request $request, $diagnosisId)
    {
        try {
            $user = $request->user();

            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Validasi diagnosis_id adalah numeric
            if (!is_numeric($diagnosisId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid diagnosis ID'
                ], 400);
            }

            // Cek apakah diagnosis ada dan milik user
            $diagnosis = Diagnosis::where('id', $diagnosisId)
                ->where('user_id', $user->id)
                ->first();

            if (!$diagnosis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Diagnosis tidak ditemukan atau bukan milik Anda'
                ], 404);
            }

            // Cari feedback
            $feedback = Feedback::where('diagnosis_id', $diagnosisId)
                ->where('user_id', $user->id)
                ->first();

            // Return success dengan data null jika belum ada feedback
            return response()->json([
                'success' => true,
                'data' => $feedback, // null jika belum ada, object jika ada
                'has_feedback' => $feedback !== null
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'diagnosis_id' => $diagnosisId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil feedback',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
