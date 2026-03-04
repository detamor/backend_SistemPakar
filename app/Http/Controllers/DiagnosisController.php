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
        try {
            $plants = Plant::where('is_active', true)->get();
            
            // Format image URLs
            $appUrl = env('APP_URL', 'http://localhost:8000');
            // Ensure port is included for localhost
            if (str_contains($appUrl, 'localhost') && !str_contains($appUrl, 'localhost:')) {
                $appUrl = str_replace('localhost', 'localhost:8000', $appUrl);
            }
            
            $plants->transform(function($plant) use ($appUrl) {
                $plantData = $plant->toArray();
                
                // Convert image path to URL
                if ($plantData['image']) {
                    if (!str_starts_with($plantData['image'], 'http')) {
                        $plantData['image'] = $appUrl . '/storage/' . ltrim($plantData['image'], '/');
                    }
                }
                
                return $plantData;
            });
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $plants
            ]));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error getting plants', [
                'error' => $e->getMessage()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar tanaman',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Mendapatkan daftar gejala
     */
    public function getSymptoms(Request $request)
    {
        try {
            $plantId = $request->query('plant_id');
            
            $query = Symptom::where('is_active', true);
            
            if ($plantId) {
                // Filter gejala berdasarkan tanaman (jika ada relasi)
                $query->whereHas('diseases', function($q) use ($plantId) {
                    $q->where('plant_id', $plantId);
                });
            }
            
            $symptoms = $query->get();
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $symptoms
            ]));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error getting symptoms', [
                'error' => $e->getMessage(),
                'plant_id' => $request->query('plant_id')
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar gejala',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
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
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422));
            
            return $response;
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
            
            Log::info('Sending diagnosis request to Python engine', [
                'python_url' => $pythonApiUrl,
                'diagnosis_id' => $diagnosis->id,
                'plant_id' => $request->plant_id,
                'symptoms_count' => count($request->symptoms)
            ]);
            
            // Siapkan data penyakit untuk dikirim ke Python engine
            // Ini menghindari Python engine memanggil kembali Laravel API (menghindari timeout)
            $diseases = Disease::where('plant_id', $request->plant_id)
                ->where('is_active', true)
                ->with(['symptoms' => function($query) {
                    $query->select('symptoms.id', 'symptoms.code', 'symptoms.description');
                }])
                ->get();
            
            $diseasesData = $diseases->map(function($disease) {
                $symptomsData = [];
                foreach ($disease->symptoms as $symptom) {
                    $symptomsData[] = [
                        'symptom_id' => $symptom->id,
                        'certainty_factor' => (float) ($symptom->pivot->certainty_factor ?? 0.0)
                    ];
                }
                
                return [
                    'id' => $disease->id,
                    'name' => $disease->name,
                    'description' => $disease->description ?? '',
                    'cause' => $disease->cause ?? '',
                    'solution' => $disease->solution ?? '',
                    'prevention' => $disease->prevention ?? '',
                    'symptoms' => $symptomsData
                ];
            })->toArray();
            
            Log::info('Prepared diseases data for Python engine', [
                'plant_id' => $request->plant_id,
                'diseases_count' => count($diseasesData)
            ]);
            
            // Kirim request ke Python engine dengan retry dan timeout yang lebih panjang
            // Gunakan withOptions untuk memastikan timeout benar-benar diterapkan
            Log::info('Preparing HTTP request to Python engine', [
                'diagnosis_id' => $diagnosis->id,
                'url' => "{$pythonApiUrl}/api/diagnose",
                'timeout' => 120,
                'connect_timeout' => 10
            ]);
            
            $startTime = microtime(true);
            
            try {
                $response = Http::withOptions([
                    'timeout' => 120, // Total timeout 120 detik
                    'connect_timeout' => 10, // Timeout koneksi 10 detik
                    'verify' => false, // Disable SSL verification untuk localhost
                ])
                ->retry(2, 1000) // Retry 2 kali dengan delay 1 detik
                ->post("{$pythonApiUrl}/api/diagnose", [
                    'diagnosis_id' => $diagnosis->id,
                    'plant_id' => $request->plant_id,
                    'symptoms' => $request->symptoms,
                    'diseases_data' => $diseasesData, // Kirim data penyakit langsung
                ]);
                
                $elapsedTime = microtime(true) - $startTime;
                Log::info('HTTP request completed', [
                    'diagnosis_id' => $diagnosis->id,
                    'elapsed_time' => round($elapsedTime, 2) . ' seconds',
                    'status_code' => $response->status()
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $elapsedTime = microtime(true) - $startTime;
                Log::error('Connection error to Python engine', [
                    'diagnosis_id' => $diagnosis->id,
                    'elapsed_time' => round($elapsedTime, 2) . ' seconds',
                    'error' => $e->getMessage(),
                    'url' => "{$pythonApiUrl}/api/diagnose"
                ]);
                throw $e;
            } catch (\Exception $e) {
                $elapsedTime = microtime(true) - $startTime;
                Log::error('HTTP request error', [
                    'diagnosis_id' => $diagnosis->id,
                    'elapsed_time' => round($elapsedTime, 2) . ' seconds',
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e)
                ]);
                throw $e;
            }

            if ($response->successful()) {
                try {
                    // Parse JSON response
                    $result = null;
                    try {
                        $result = $response->json();
                    } catch (\Exception $jsonError) {
                        Log::error('Error parsing JSON response from Python engine', [
                            'diagnosis_id' => $diagnosis->id,
                            'error' => $jsonError->getMessage(),
                            'response_body' => $response->body()
                        ]);
                        throw new \Exception('Gagal memparse response dari Python engine: ' . $jsonError->getMessage());
                    }
                    
                    Log::info('Python engine response parsed', [
                        'diagnosis_id' => $diagnosis->id,
                        'has_success' => isset($result['success']),
                        'has_data' => isset($result['data']),
                        'result_keys' => $result ? array_keys($result) : []
                    ]);
                    
                    // Validasi response dari Python engine
                    if (!is_array($result)) {
                        Log::error('Python engine response bukan array', [
                            'diagnosis_id' => $diagnosis->id,
                            'response_type' => gettype($result),
                            'response' => $result
                        ]);
                        throw new \Exception('Python engine mengembalikan response tidak valid (bukan array)');
                    }
                    
                    if (!isset($result['success']) || !$result['success']) {
                        Log::warning('Python engine mengembalikan response tidak sukses', [
                            'diagnosis_id' => $diagnosis->id,
                            'response' => $result
                        ]);
                        throw new \Exception('Python engine mengembalikan response tidak valid: ' . json_encode($result));
                    }
                    
                    if (!isset($result['data'])) {
                        Log::warning('Python engine response tidak memiliki data', [
                            'diagnosis_id' => $diagnosis->id,
                            'response' => $result
                        ]);
                        throw new \Exception('Python engine response tidak memiliki data');
                    }
                    
                    // Update diagnosis dengan hasil
                    $diseaseId = $result['data']['disease_id'] ?? null;
                    $certaintyValue = $result['data']['certainty_value'] ?? 0;
                    $recommendation = $result['data']['recommendation'] ?? null;
                    $allPossibilities = $result['data']['all_possibilities'] ?? [];
                    
                    // Hitung matched symptoms count dari all_possibilities
                    $matchedSymptomsCount = 0;
                    if (!empty($allPossibilities) && isset($allPossibilities[0])) {
                        $matchedSymptomsCount = $allPossibilities[0]['matched_count'] ?? 0;
                    }
                    
                    $updateData = [
                        'disease_id' => $diseaseId,
                        'certainty_value' => $certaintyValue,
                        'recommendation' => $recommendation,
                        'all_possibilities_json' => $allPossibilities,
                        'matched_symptoms_count' => $matchedSymptomsCount,
                        'status' => 'completed',
                    ];
                    
                    Log::info('Updating diagnosis with result', [
                        'diagnosis_id' => $diagnosis->id,
                        'update_data' => $updateData
                    ]);
                    
                    try {
                        $diagnosis->update($updateData);
                        Log::info('Diagnosis record updated in database');
                    } catch (\Exception $updateError) {
                        Log::error('Error updating diagnosis in database', [
                            'diagnosis_id' => $diagnosis->id,
                            'error' => $updateError->getMessage(),
                            'trace' => $updateError->getTraceAsString()
                        ]);
                        throw new \Exception('Gagal mengupdate diagnosis: ' . $updateError->getMessage());
                    }

                    // Load relasi untuk response
                    try {
                        $diagnosis->load(['disease', 'plant', 'symptoms']);
                        Log::info('Relations loaded successfully');
                    } catch (\Exception $loadError) {
                        Log::warning('Error loading relations, continuing anyway', [
                            'diagnosis_id' => $diagnosis->id,
                            'error' => $loadError->getMessage()
                        ]);
                        // Continue even if relations fail to load
                    }

                    Log::info('Diagnosis updated successfully', [
                        'diagnosis_id' => $diagnosis->id,
                        'disease_id' => $diagnosis->disease_id,
                        'certainty_value' => $diagnosis->certainty_value
                    ]);

                    // Prepare response data
                    try {
                        // Convert model ke array untuk menghindari serialization error
                        $diagnosisArray = $diagnosis->toArray();
                        $diseaseArray = $diagnosis->disease ? $diagnosis->disease->toArray() : null;
                        $plantArray = $diagnosis->plant ? $diagnosis->plant->toArray() : null;
                        $symptomsArray = $diagnosis->symptoms ? $diagnosis->symptoms->map(function($symptom) {
                            return [
                                'id' => $symptom->id,
                                'code' => $symptom->code,
                                'description' => $symptom->description,
                                'user_cf' => $symptom->pivot->user_cf ?? null
                            ];
                        })->toArray() : [];
                        
                        // Gunakan data dari database (sudah disimpan) untuk konsistensi
                        $responseData = [
                            'diagnosis' => $diagnosisArray,
                            'disease' => $diseaseArray,
                            'plant' => $plantArray,
                            'symptoms' => $symptomsArray,
                            'certainty_value' => (float) $diagnosis->certainty_value,
                            'recommendation' => $diagnosis->recommendation ?? null,
                            'all_possibilities' => $diagnosis->all_possibilities_json ?? [],
                            'matched_symptoms_count' => $diagnosis->matched_symptoms_count ?? 0,
                        ];
                        
                        // Log untuk debugging
                        Log::info('Response data prepared', [
                            'diagnosis_id' => $diagnosis->id,
                            'has_recommendation' => !empty($diagnosis->recommendation),
                            'has_all_possibilities' => !empty($diagnosis->all_possibilities_json),
                            'matched_symptoms_count' => $diagnosis->matched_symptoms_count ?? 0
                        ]);
                        
                        Log::info('Preparing response data', [
                            'diagnosis_id' => $diagnosis->id,
                            'has_disease' => $diseaseArray !== null,
                            'disease_id' => $diagnosis->disease_id
                        ]);

                        $this->addCorsHeaders($response = response()->json([
                            'success' => true,
                            'data' => $responseData
                        ], 200));
                        
                        Log::info('Response prepared successfully, returning to client');
                        return $response;
                    } catch (\Exception $responseError) {
                        Log::error('Error preparing response data', [
                            'diagnosis_id' => $diagnosis->id,
                            'error' => $responseError->getMessage(),
                            'trace' => $responseError->getTraceAsString(),
                            'file' => $responseError->getFile(),
                            'line' => $responseError->getLine()
                        ]);
                        throw new \Exception('Gagal menyiapkan response: ' . $responseError->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing Python engine response', [
                        'diagnosis_id' => $diagnosis->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    throw $e;
                }
            }

            // Jika Python API gagal, tetap simpan diagnosis
            Log::warning('Python API tidak tersedia, diagnosis disimpan tanpa hasil', [
                'diagnosis_id' => $diagnosis->id,
                'response_status' => $response->status(),
                'response_body' => $response->body()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Engine diagnosis tidak tersedia. Pastikan Python engine running di port 8001. Silakan konsultasi dengan pakar.',
                'diagnosis_id' => $diagnosis->id
            ], 503));
            
            return $response;

        } catch (\Exception $e) {
            Log::error('Error dalam proses diagnosis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan dalam proses diagnosis',
                'message' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Mendapatkan riwayat diagnosis user
     */
    public function getHistory(Request $request)
    {
        try {
            $user = auth()->user();
            
            $diagnoses = Diagnosis::where('user_id', $user->id)
                ->with(['plant', 'disease', 'symptoms', 'feedback'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            // Format data untuk response (termasuk recommendation dan all_possibilities)
            $formattedData = $diagnoses->getCollection()->map(function($diagnosis) {
                return [
                    'id' => $diagnosis->id,
                    'plant_id' => $diagnosis->plant_id,
                    'disease_id' => $diagnosis->disease_id,
                    'certainty_value' => (float) $diagnosis->certainty_value,
                    'recommendation' => $diagnosis->recommendation,
                    'all_possibilities' => $diagnosis->all_possibilities_json ?? [],
                    'matched_symptoms_count' => $diagnosis->matched_symptoms_count ?? 0,
                    'user_notes' => $diagnosis->user_notes,
                    'status' => $diagnosis->status,
                    'created_at' => $diagnosis->created_at,
                    'updated_at' => $diagnosis->updated_at,
                    'plant' => $diagnosis->plant ? [
                        'id' => $diagnosis->plant->id,
                        'name' => $diagnosis->plant->name,
                        'scientific_name' => $diagnosis->plant->scientific_name,
                    ] : null,
                    'disease' => $diagnosis->disease ? [
                        'id' => $diagnosis->disease->id,
                        'name' => $diagnosis->disease->name,
                        'code' => $diagnosis->disease->code,
                    ] : null,
                    'feedback' => $diagnosis->feedback,
                ];
            });

            // Replace collection dengan formatted data
            $diagnoses->setCollection($formattedData);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $diagnoses
            ]));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error getting diagnosis history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil riwayat diagnosis',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Mendapatkan detail diagnosis
     */
    public function getDetail($id)
    {
        try {
            $diagnosis = Diagnosis::with(['plant', 'disease', 'symptoms', 'feedback'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            // Format response dengan data lengkap
            $responseData = [
                'id' => $diagnosis->id,
                'user_id' => $diagnosis->user_id,
                'plant_id' => $diagnosis->plant_id,
                'disease_id' => $diagnosis->disease_id,
                'certainty_value' => (float) $diagnosis->certainty_value,
                'recommendation' => $diagnosis->recommendation,
                'all_possibilities' => $diagnosis->all_possibilities_json ?? [],
                'matched_symptoms_count' => $diagnosis->matched_symptoms_count ?? 0,
                'user_notes' => $diagnosis->user_notes,
                'status' => $diagnosis->status,
                'created_at' => $diagnosis->created_at,
                'updated_at' => $diagnosis->updated_at,
                'plant' => $diagnosis->plant ? [
                    'id' => $diagnosis->plant->id,
                    'name' => $diagnosis->plant->name,
                    'scientific_name' => $diagnosis->plant->scientific_name,
                ] : null,
                'disease' => $diagnosis->disease ? [
                    'id' => $diagnosis->disease->id,
                    'name' => $diagnosis->disease->name,
                    'code' => $diagnosis->disease->code,
                    'description' => $diagnosis->disease->description,
                    'cause' => $diagnosis->disease->cause,
                    'solution' => $diagnosis->disease->solution,
                    'prevention' => $diagnosis->disease->prevention,
                ] : null,
                'symptoms' => $diagnosis->symptoms ? $diagnosis->symptoms->map(function($symptom) {
                    return [
                        'id' => $symptom->id,
                        'code' => $symptom->code,
                        'description' => $symptom->description,
                        'category' => $symptom->category,
                        'user_cf' => $symptom->pivot->user_cf ?? null,
                    ];
                })->toArray() : [],
                'feedback' => $diagnosis->feedback,
            ];

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $responseData,
                'note' => $diagnosis->status === 'pending' ? 'Diagnosis masih dalam proses. Pastikan Python engine running untuk mendapatkan hasil.' : null
            ]));
            
            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Diagnosis tidak ditemukan'
            ], 404));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error getting diagnosis detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'diagnosis_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail diagnosis',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Download PDF laporan diagnosis
     */
    public function downloadPdf($id)
    {
        try {
            $diagnosis = Diagnosis::with(['plant', 'disease', 'symptoms'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            // Generate PDF using dompdf
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.pdf', [
                'diagnosis' => $diagnosis,
                'user' => auth()->user()
            ]);

            $filename = 'diagnosis-' . $diagnosis->id . '-' . date('Y-m-d') . '.pdf';

            // Set headers for CORS and download
            $response = $pdf->download($filename);
            $this->addCorsHeaders($response);
            
            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Diagnosis tidak ditemukan'
            ], 404));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error downloading PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'diagnosis_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh PDF: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500));
            
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
}



