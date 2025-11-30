<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Submit feedback untuk diagnosis
     */
    public function store(Request $request)
    {
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

        $user = auth()->user();

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
    }

    /**
     * Get feedback untuk diagnosis tertentu
     */
    public function show($diagnosisId)
    {
        $user = auth()->user();

        $feedback = Feedback::where('diagnosis_id', $diagnosisId)
            ->where('user_id', $user->id)
            ->first();

        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $feedback
        ], 200);
    }
}

