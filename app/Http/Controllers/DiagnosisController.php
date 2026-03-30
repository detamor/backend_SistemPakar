<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\Disease;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DiagnosisController extends Controller
{
    /**
     * Catatan penting:
     * - Endpoint /api/diagnosis bersifat public untuk mendukung user tanpa login (guest).
     * - Jika request membawa Bearer token Sanctum yang valid, maka alur "login" dipakai (hasil disimpan ke DB + riwayat aktif).
     * - Jika tidak ada user terautentikasi, maka alur "guest" dipakai (hasil tidak disimpan ke tabel diagnoses; hanya dikembalikan di response).
     */

    /**
     * Lebih dari satu hipotesis tertinggi dengan CF yang sama: penyakit dengan nilai CF puncak yang sama, urut sesuai all_possibilities.
     * Digunakan agar UI/PDF bisa menampilkan solusi lengkap untuk masing-masing hipotesis dengan CF tertinggi sama.
     */
    private function buildTiedTopDiseasesFromPossibilities(?array $possibilities, int $plantId): array
    {
        if (empty($possibilities)) {
            return [];
        }

        $maxCf = -1.0;
        foreach ($possibilities as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cf = (float) ($row['certainty_value'] ?? 0);
            if ($cf > $maxCf) {
                $maxCf = $cf;
            }
        }

        if ($maxCf < 0) {
            return [];
        }

        $eps = 1e-4;
        $orderedIds = [];
        $rowById = [];
        foreach ($possibilities as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cf = (float) ($row['certainty_value'] ?? 0);
            if (abs($cf - $maxCf) > $eps) {
                continue;
            }
            $did = $row['disease_id'] ?? null;
            if ($did === null) {
                continue;
            }
            $did = (int) $did;
            if (isset($rowById[$did])) {
                continue;
            }
            $rowById[$did] = $row;
            $orderedIds[] = $did;
        }

        if ($orderedIds === []) {
            return [];
        }

        $models = Disease::whereIn('id', $orderedIds)
            ->where('plant_id', $plantId)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($orderedIds as $did) {
            if (! $models->has($did)) {
                continue;
            }
            $dis = $models->get($did);
            $row = $rowById[$did];
            $out[] = [
                'id' => $dis->id,
                'name' => $dis->name,
                'code' => $dis->code,
                'description' => $dis->description,
                'cause' => $dis->cause,
                'solution' => $dis->solution ?: (string) ($row['solution'] ?? ''),
                'prevention' => $dis->prevention ?: (string) ($row['prevention'] ?? ''),
                'certainty_value' => (float) ($row['certainty_value'] ?? 0),
                'matched_symptoms_count' => (int) ($row['matched_count'] ?? 0),
            ];
        }

        return $out;
    }

    private function buildHighConfidenceDiseasesFromPossibilities(?array $possibilities, int $plantId, float $threshold): array
    {
        if (empty($possibilities)) {
            return [];
        }

        $orderedIds = [];
        $rowById = [];
        foreach ($possibilities as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cf = (float) ($row['certainty_value'] ?? 0);
            if ($cf < $threshold) {
                continue;
            }
            $did = $row['disease_id'] ?? null;
            if ($did === null) {
                continue;
            }
            $did = (int) $did;
            if (isset($rowById[$did])) {
                continue;
            }
            $rowById[$did] = $row;
            $orderedIds[] = $did;
        }

        if ($orderedIds === []) {
            return [];
        }

        $models = Disease::whereIn('id', $orderedIds)
            ->where('plant_id', $plantId)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($orderedIds as $did) {
            if (! $models->has($did)) {
                continue;
            }
            $dis = $models->get($did);
            $row = $rowById[$did];
            $out[] = [
                'id' => $dis->id,
                'name' => $dis->name,
                'code' => $dis->code,
                'description' => $dis->description,
                'cause' => $dis->cause,
                'solution' => $dis->solution ?: (string) ($row['solution'] ?? ''),
                'prevention' => $dis->prevention ?: (string) ($row['prevention'] ?? ''),
                'certainty_value' => (float) ($row['certainty_value'] ?? 0),
                'matched_symptoms_count' => (int) ($row['matched_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Mendapatkan daftar tanaman
     */
    public function getPlants()
    {
        try {
            $plants = Plant::where('is_active', true)->get();

            // Format image URLs (pakai relative /storage supaya tidak bergantung APP_URL/IP)
            $plants->transform(function($plant) {
                $plantData = $plant->toArray();
                
                // Convert image path to URL
                if ($plantData['image']) {
                    if (!str_starts_with($plantData['image'], 'http')) {
                        $plantData['image'] = '/storage/' . ltrim($plantData['image'], '/');
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
                $query->where(function ($q) use ($plantId) {
                    $q->where('plant_id', $plantId)
                        ->orWhereHas('diseases', function ($q2) use ($plantId) {
                            $q2->where('plant_id', $plantId);
                        });
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
            $normalizedNotes = null;
            if ($request->filled('user_notes')) {
                $trimmedNotes = trim((string) $request->user_notes);
                $normalizedNotes = $trimmedNotes !== '' ? $trimmedNotes : null;
            }

            // Ambil user dari token Sanctum jika ada (meskipun route ini public).
            // Ini mencegah kasus fatal: user login dianggap guest karena guard default tidak aktif.
            $user = Auth::guard('sanctum')->user();
            if (! $user instanceof User) {
                $user = $request->user();
            }

            // ====== ALUR GUEST (tanpa login) ======
            // - Tidak membuat record diagnosis di database.
            // - Tetap memanggil Python engine untuk menghitung hasil.
            // - Mengembalikan "is_guest: true" + diagnosis_id = null.
            if (! $user instanceof User) {
                $pythonApiUrl = env('PYTHON_API_URL', 'http://localhost:8001');

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

                $response = Http::withOptions([
                    'timeout' => 120,
                    'connect_timeout' => 10,
                    'verify' => false,
                ])
                ->retry(2, 1000)
                ->post("{$pythonApiUrl}/api/diagnose", [
                    'diagnosis_id' => 0,
                    'plant_id' => $request->plant_id,
                    'symptoms' => $request->symptoms,
                    'diseases_data' => $diseasesData,
                ]);

                if (! $response->successful()) {
                    $this->addCorsHeaders($r = response()->json([
                        'success' => false,
                        'message' => 'Engine diagnosis tidak tersedia. Pastikan Python engine running di port 8001.',
                    ], 503));
                    return $r;
                }

                $result = $response->json();
                if (!is_array($result) || !($result['success'] ?? false) || !isset($result['data'])) {
                    $this->addCorsHeaders($r = response()->json([
                        'success' => false,
                        'message' => 'Python engine mengembalikan response tidak valid.',
                    ], 500));
                    return $r;
                }

                $diseaseId = $result['data']['disease_id'] ?? null;
                $certaintyValue = (float) ($result['data']['certainty_value'] ?? 0);
                $recommendation = $result['data']['recommendation'] ?? null;
                $allPossibilities = $result['data']['all_possibilities'] ?? [];
                $matchedSymptomsCount = 0;
                if (is_array($allPossibilities) && !empty($allPossibilities) && isset($allPossibilities[0])) {
                    $matchedSymptomsCount = (int) ($allPossibilities[0]['matched_count'] ?? 0);
                }

                $plant = Plant::find($request->plant_id);
                $disease = $diseaseId ? Disease::find($diseaseId) : null;

                $symptomIds = collect($request->symptoms)->pluck('symptom_id')->filter()->unique()->values()->all();
                $symptoms = Symptom::whereIn('id', $symptomIds)->get()->keyBy('id');
                $symptomsArray = [];
                foreach ($request->symptoms as $symptomData) {
                    $sid = (int) ($symptomData['symptom_id'] ?? 0);
                    if (!$sid || !$symptoms->has($sid)) {
                        continue;
                    }
                    $s = $symptoms->get($sid);
                    $symptomsArray[] = [
                        'id' => $s->id,
                        'code' => $s->code,
                        'description' => $s->description,
                        'user_cf' => (float) ($symptomData['user_cf'] ?? 0),
                    ];
                }

                $responseData = [
                    'diagnosis' => [
                        'id' => null,
                        'user_id' => null,
                        'plant_id' => (int) $request->plant_id,
                        'disease_id' => $disease ? $disease->id : null,
                        'certainty_value' => $certaintyValue,
                        'recommendation' => $recommendation,
                        'all_possibilities' => $allPossibilities,
                        'matched_symptoms_count' => $matchedSymptomsCount,
                        'user_notes' => $normalizedNotes,
                        'status' => 'completed',
                    ],
                    'disease' => $disease ? $disease->toArray() : null,
                    'plant' => $plant ? $plant->toArray() : null,
                    'symptoms' => $symptomsArray,
                    'certainty_value' => $certaintyValue,
                    'recommendation' => $recommendation,
                    'all_possibilities' => $allPossibilities,
                    'matched_symptoms_count' => $matchedSymptomsCount,
                    'tied_diseases' => $this->buildTiedTopDiseasesFromPossibilities(
                        is_array($allPossibilities) ? $allPossibilities : [],
                        (int) $request->plant_id
                    ),
                    'high_confidence_diseases' => $this->buildHighConfidenceDiseasesFromPossibilities(
                        is_array($allPossibilities) ? $allPossibilities : [],
                        (int) $request->plant_id,
                        0.7
                    ),
                ];

                if ($normalizedNotes !== null) {
                    try {
                        // Catatan tambahan guest disimpan terpisah untuk admin (feedback) tanpa membuat diagnosis history.
                        DB::table('guest_evaluation_notes')->insert([
                            'plant_id' => (int) $request->plant_id,
                            'disease_id' => $disease ? (int) $disease->id : null,
                            'certainty_value' => $certaintyValue,
                            'user_notes' => $normalizedNotes,
                            'selected_symptoms_json' => json_encode($request->symptoms),
                            'ip_address' => (string) $request->ip(),
                            'user_agent' => substr((string) $request->userAgent(), 0, 255),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to store guest evaluation note', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->addCorsHeaders($r = response()->json([
                    'success' => true,
                    'diagnosis_id' => null,
                    'data' => $responseData,
                    'is_guest' => true,
                ], 200));
                return $r;
            }

            // ====== ALUR LOGIN (user terautentikasi) ======
            // - Membuat record diagnosis di database.
            // - Menyimpan gejala dipilih user ke pivot diagnosis_symptoms.
            // - Memanggil Python engine dengan diagnosis_id yang valid supaya hasil tersimpan untuk riwayat dan PDF lengkap.
            $diagnosis = Diagnosis::create([
                'user_id' => $user->id,
                'plant_id' => $request->plant_id,
                'user_notes' => $normalizedNotes,
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
                            'tied_diseases' => $this->buildTiedTopDiseasesFromPossibilities(
                                $diagnosis->all_possibilities_json ?? [],
                                (int) $diagnosis->plant_id
                            ),
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
                            'diagnosis_id' => $diagnosis->id,
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
            // Endpoint ini hanya untuk user login; guest tidak punya riwayat.
            $user = $request->user();
            if (! $user instanceof User) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401));
                return $response;
            }
            
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
    public function getDetail(Request $request, $id)
    {
        try {
            // Endpoint ini hanya untuk user login; guest tidak punya diagnosis_id.
            $user = $request->user();
            if (! $user instanceof User) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401));
                return $response;
            }

            $diagnosis = Diagnosis::with(['plant', 'disease', 'symptoms', 'feedback'])
                ->where('user_id', $user->id)
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
                'tied_diseases' => $this->buildTiedTopDiseasesFromPossibilities(
                    $diagnosis->all_possibilities_json ?? [],
                    (int) $diagnosis->plant_id
                ),
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
     * Update / create catatan evaluasi user pada diagnosis.
     */
    public function updateNotes(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422));

            return $response;
        }

        try {
            $user = $request->user();
            if (! $user instanceof User) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401));
                return $response;
            }

            $diagnosis = Diagnosis::where('user_id', $user->id)->findOrFail($id);

            $normalizedNotes = trim((string) $request->user_notes);

            if ($normalizedNotes === '') {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'message' => 'Catatan tidak boleh kosong.'
                ], 422));

                return $response;
            }

            $diagnosis->update([
                'user_notes' => $normalizedNotes
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Catatan evaluasi berhasil disimpan.',
                'data' => [
                    'id' => $diagnosis->id,
                    'user_notes' => $diagnosis->user_notes,
                    'updated_at' => $diagnosis->updated_at,
                ]
            ]));

            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Diagnosis tidak ditemukan'
            ], 404));

            return $response;
        } catch (\Exception $e) {
            Log::error('Error updating diagnosis notes', [
                'diagnosis_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan catatan.',
                'error' => $e->getMessage()
            ], 500));

            return $response;
        }
    }

    /**
     * Hapus catatan evaluasi user pada diagnosis.
     */
    public function deleteNotes(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (! $user instanceof User) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401));
                return $response;
            }

            $diagnosis = Diagnosis::where('user_id', $user->id)->findOrFail($id);

            $diagnosis->update([
                'user_notes' => null
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Catatan evaluasi berhasil dihapus.'
            ]));

            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Diagnosis tidak ditemukan'
            ], 404));

            return $response;
        } catch (\Exception $e) {
            Log::error('Error deleting diagnosis notes', [
                'diagnosis_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus catatan.',
                'error' => $e->getMessage()
            ], 500));

            return $response;
        }
    }

    /**
     * Download PDF laporan diagnosis
     */
    public function downloadPdf(Request $request, $id)
    {
        try {
            // PDF "login" mengambil diagnosis dari database, jadi wajib auth.
            $user = $request->user();
            if (! $user instanceof User) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401));
                return $response;
            }

            $diagnosis = Diagnosis::with(['plant', 'disease', 'symptoms'])
                ->where('user_id', $user->id)
                ->findOrFail($id);

            // Generate PDF using dompdf
            $tiedTopDiseases = $this->buildTiedTopDiseasesFromPossibilities(
                $diagnosis->all_possibilities_json ?? [],
                (int) $diagnosis->plant_id
            );

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.pdf', [
                'diagnosis' => $diagnosis,
                'user' => $user,
                'tiedTopDiseases' => $tiedTopDiseases,
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
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition, Content-Type');
        
        return $response;
    }

    public function downloadGuestPdf(Request $request)
    {
        // Endpoint guest versi "full" (menghitung ulang ke Python engine) untuk kasus fallback.
        // Saat ini UI lebih sering memakai pdf-simple agar tidak bergantung engine.
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
            $normalizedNotes = null;
            if ($request->filled('user_notes')) {
                $trimmedNotes = trim((string) $request->user_notes);
                $normalizedNotes = $trimmedNotes !== '' ? $trimmedNotes : null;
            }

            $pythonApiUrl = env('PYTHON_API_URL', 'http://localhost:8001');

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

            $response = Http::withOptions([
                'timeout' => 120,
                'connect_timeout' => 10,
                'verify' => false,
            ])
            ->retry(2, 1000)
            ->post("{$pythonApiUrl}/api/diagnose", [
                'diagnosis_id' => 0,
                'plant_id' => $request->plant_id,
                'symptoms' => $request->symptoms,
                'diseases_data' => $diseasesData,
            ]);

            if (! $response->successful()) {
                $this->addCorsHeaders($r = response()->json([
                    'success' => false,
                    'message' => 'Engine diagnosis tidak tersedia. Pastikan Python engine running di port 8001.',
                ], 503));
                return $r;
            }

            $result = $response->json();
            if (!is_array($result) || !($result['success'] ?? false) || !isset($result['data'])) {
                $this->addCorsHeaders($r = response()->json([
                    'success' => false,
                    'message' => 'Python engine mengembalikan response tidak valid.',
                ], 500));
                return $r;
            }

            $diseaseId = $result['data']['disease_id'] ?? null;
            $certaintyValue = (float) ($result['data']['certainty_value'] ?? 0);
            $recommendation = $result['data']['recommendation'] ?? null;
            $allPossibilities = $result['data']['all_possibilities'] ?? [];

            $plant = Plant::find($request->plant_id);
            $disease = $diseaseId ? Disease::find($diseaseId) : null;

            $symptomIds = collect($request->symptoms)->pluck('symptom_id')->filter()->unique()->values()->all();
            $symptoms = Symptom::whereIn('id', $symptomIds)->get()->keyBy('id');
            $symptomsArray = [];
            foreach ($request->symptoms as $symptomData) {
                $sid = (int) ($symptomData['symptom_id'] ?? 0);
                if (!$sid || !$symptoms->has($sid)) {
                    continue;
                }
                $s = $symptoms->get($sid);
                $symptomsArray[] = (object) [
                    'id' => $s->id,
                    'code' => $s->code,
                    'description' => $s->description,
                    'pivot' => (object) [
                        'user_cf' => (float) ($symptomData['user_cf'] ?? 0),
                    ],
                ];
            }

            $diagnosis = (object) [
                'id' => 'GUEST',
                'plant_id' => (int) $request->plant_id,
                'disease_id' => $disease ? $disease->id : null,
                'certainty_value' => $certaintyValue,
                'recommendation' => $recommendation,
                'all_possibilities_json' => is_array($allPossibilities) ? $allPossibilities : [],
                'user_notes' => $normalizedNotes,
                'status' => 'completed',
                'plant' => $plant,
                'disease' => $disease,
                'symptoms' => $symptomsArray,
            ];

            $guestUser = (object) [
                'name' => 'Guest',
                'email' => '-',
            ];

            $tiedTopDiseases = $this->buildTiedTopDiseasesFromPossibilities(
                is_array($allPossibilities) ? $allPossibilities : [],
                (int) $request->plant_id
            );

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.pdf', [
                'diagnosis' => $diagnosis,
                'user' => $guestUser,
                'tiedTopDiseases' => $tiedTopDiseases,
            ]);

            $filename = 'diagnosis-guest-' . date('Y-m-d') . '.pdf';
            $download = $pdf->download($filename);
            $this->addCorsHeaders($download);
            return $download;
        } catch (\Exception $e) {
            Log::error('Error downloading guest PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh PDF: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500));

            return $response;
        }
    }

    public function downloadGuestSimplePdf(Request $request)
    {
        // Endpoint guest versi "ringkas":
        // - Tidak memanggil Python engine.
        // - Menerima ringkasan hasil yang sudah tampil di UI guest, lalu render PDF.
        $validator = Validator::make($request->all(), [
            'plant_name' => 'nullable|string|max:255',
            'disease_name' => 'nullable|string|max:255',
            'certainty_value' => 'nullable|numeric|min:0|max:1',
            'matched_symptoms_count' => 'nullable|integer|min:0|max:999',
            'all_possibilities' => 'nullable|array|max:20',
            'all_possibilities.*.disease_name' => 'required_with:all_possibilities|string|max:255',
            'all_possibilities.*.certainty_value' => 'required_with:all_possibilities|numeric|min:0|max:1',
            'high_confidence_diseases' => 'nullable|array|max:10',
            'high_confidence_diseases.*.name' => 'required_with:high_confidence_diseases|string|max:255',
            'high_confidence_diseases.*.certainty_value' => 'required_with:high_confidence_diseases|numeric|min:0|max:1',
            'high_confidence_diseases.*.matched_symptoms_count' => 'nullable|integer|min:0|max:999',
            'high_confidence_diseases.*.description' => 'nullable|string|max:5000',
            'high_confidence_diseases.*.cause' => 'nullable|string|max:5000',
            'high_confidence_diseases.*.solution' => 'nullable|string|max:8000',
            'high_confidence_diseases.*.prevention' => 'nullable|string|max:8000',
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
            $data = [
                'plant_name' => $request->input('plant_name'),
                'disease_name' => $request->input('disease_name'),
                'certainty_value' => (float) ($request->input('certainty_value') ?? 0),
                'matched_symptoms_count' => (int) ($request->input('matched_symptoms_count') ?? 0),
                'all_possibilities' => array_slice((array) ($request->input('all_possibilities') ?? []), 0, 10),
                'high_confidence_diseases' => array_slice((array) ($request->input('high_confidence_diseases') ?? []), 0, 10),
                'user_notes' => $request->input('user_notes'),
                'generated_at' => now(),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.guest_simple_pdf', [
                'data' => $data,
            ]);

            $filename = 'diagnosis-guest-' . date('Y-m-d') . '.pdf';
            $download = $pdf->download($filename);
            $this->addCorsHeaders($download);
            return $download;
        } catch (\Exception $e) {
            Log::error('Error downloading guest simple PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh PDF: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500));

            return $response;
        }
    }

    public function guestWhatsAppLink(Request $request)
    {
        // Endpoint guest untuk membuat deep-link WhatsApp (wa.me) dengan format teks hasil diagnosis.
        $validator = Validator::make($request->all(), [
            'plant_id' => 'required|exists:plants,id',
            'symptoms' => 'required|array|min:1',
            'symptoms.*.symptom_id' => 'required|exists:symptoms,id',
            'symptoms.*.user_cf' => 'required|numeric|min:0|max:1',
            'user_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            $response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
            $this->addCorsHeaders($response);
            return $response;
        }

        $expertWhatsapp = env('EXPERT_WHATSAPP_NUMBER', null);
        if (! $expertWhatsapp) {
            $response = response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp pakar belum dikonfigurasi'
            ], 400);
            $this->addCorsHeaders($response);
            return $response;
        }

        $normalizedNotes = null;
        if ($request->filled('user_notes')) {
            $trimmedNotes = trim((string) $request->user_notes);
            $normalizedNotes = $trimmedNotes !== '' ? $trimmedNotes : null;
        }

        $plant = Plant::find($request->plant_id);
        $symptomIds = collect($request->symptoms)->pluck('symptom_id')->filter()->unique()->values()->all();
        $symptoms = Symptom::whereIn('id', $symptomIds)->get()->keyBy('id');

        $symptomLines = [];
        foreach ($request->symptoms as $symptomData) {
            $sid = (int) ($symptomData['symptom_id'] ?? 0);
            if (!$sid || !$symptoms->has($sid)) {
                continue;
            }
            $s = $symptoms->get($sid);
            $pct = (float) ($symptomData['user_cf'] ?? 0) * 100;
            $symptomLines[] = "- {$s->code} {$s->description} (" . number_format($pct, 0) . "%)";
        }

        $message = "🔔 *Konsultasi dari Pengguna (Guest) - System Pakar*\n\n";
        $message .= "👤 *Pengguna:* Guest\n";
        $message .= "🌱 *Tanaman:* " . ($plant?->name ?? '-') . "\n\n";
        $message .= "🩺 *Gejala dipilih:*\n" . (count($symptomLines) ? implode("\n", $symptomLines) : '-') . "\n\n";
        if ($normalizedNotes !== null) {
            $message .= "📝 *Catatan Tambahan:*\n{$normalizedNotes}\n\n";
        }
        $message .= "Mohon bantuan analisis dan saran penanganan.";

        $digitsOnly = preg_replace('/[^0-9]/', '', $expertWhatsapp);
        if (str_starts_with($digitsOnly, '0')) {
            $digitsOnly = '62' . substr($digitsOnly, 1);
        } elseif (! str_starts_with($digitsOnly, '62')) {
            $digitsOnly = '62' . $digitsOnly;
        }

        $url = 'https://wa.me/' . $digitsOnly . '?text=' . rawurlencode($message);

        $response = response()->json([
            'success' => true,
            'whatsapp_url' => $url
        ], 200);
        $this->addCorsHeaders($response);
        return $response;
    }
}
