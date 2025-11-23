<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\Disease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DiagnosisController extends Controller
{
    /**
     * Mendapatkan daftar tanaman
     */
    public function getPlants()
    {
        $plants = Plant::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $plants
        ]);
    }

    /**
     * Mendapatkan daftar gejala
     */
    public function getSymptoms(Request $request)
    {
        $plantId = $request->query('plant_id');
        
        $query = Symptom::where('is_active', true);
        
        if ($plantId) {
            // Filter gejala berdasarkan tanaman (jika ada relasi)
            $query->whereHas('diseases', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            });
        }
        
        $symptoms = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $symptoms
        ]);
    }

    /**
     * Proses diagnosis penyakit
     */
    public function diagnose(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plant_id' => 'required|exists:plants,id',
            'symptoms' => 'required|array|min:1',
            'symptoms.*.symptom_id' => 'required|exists:symptoms,id',
            'symptoms.*.user_cf' => 'required|numeric|min:0|max:1',
            'user_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Buat diagnosis record
            $diagnosis = Diagnosis::create([
                'user_id' => auth()->id(),
                'plant_id' => $request->plant_id,
                'user_notes' => $request->user_notes,
                'status' => 'pending',
            ]);

            // Simpan gejala yang dipilih user
            foreach ($request->symptoms as $symptomData) {
                $diagnosis->symptoms()->attach($symptomData['symptom_id'], [
                    'user_cf' => $symptomData['user_cf']
                ]);
            }

            // Kirim ke Python engine untuk proses Certainty Factor
            $pythonApiUrl = env('PYTHON_API_URL', 'http://localhost:8001');
            
            $response = Http::post("{$pythonApiUrl}/api/diagnose", [
                'diagnosis_id' => $diagnosis->id,
                'plant_id' => $request->plant_id,
                'symptoms' => $request->symptoms,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Update diagnosis dengan hasil
                $diagnosis->update([
                    'disease_id' => $result['data']['disease_id'] ?? null,
                    'certainty_value' => $result['data']['certainty_value'] ?? 0,
                    'status' => 'completed',
                ]);

                // Load relasi untuk response
                $diagnosis->load(['disease', 'plant', 'symptoms']);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'diagnosis' => $diagnosis,
                        'disease' => $diagnosis->disease,
                        'certainty_value' => $diagnosis->certainty_value,
                        'recommendation' => $result['data']['recommendation'] ?? null,
                    ]
                ], 200);
            }

            // Jika Python API gagal, tetap simpan diagnosis
            Log::warning('Python API tidak tersedia, diagnosis disimpan tanpa hasil', [
                'diagnosis_id' => $diagnosis->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Engine diagnosis tidak tersedia, silakan konsultasi dengan pakar',
                'diagnosis_id' => $diagnosis->id
            ], 503);

        } catch (\Exception $e) {
            Log::error('Error dalam proses diagnosis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan dalam proses diagnosis',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan riwayat diagnosis user
     */
    public function getHistory(Request $request)
    {
        $user = auth()->user();
        
        $diagnoses = Diagnosis::where('user_id', $user->id)
            ->with(['plant', 'disease', 'symptoms'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $diagnoses
        ]);
    }

    /**
     * Mendapatkan detail diagnosis
     */
    public function getDetail($id)
    {
        $diagnosis = Diagnosis::with(['plant', 'disease', 'symptoms', 'feedback'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $diagnosis
        ]);
    }

    /**
     * Download PDF laporan diagnosis
     */
    public function downloadPdf($id)
    {
        $diagnosis = Diagnosis::with(['plant', 'disease', 'symptoms'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // TODO: Implementasi PDF generation
        // Bisa menggunakan library seperti dompdf atau barryvdh/laravel-dompdf
        
        return response()->json([
            'success' => true,
            'message' => 'PDF generation akan diimplementasikan',
            'data' => $diagnosis
        ]);
    }
}



