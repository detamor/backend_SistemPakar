<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disease;
use App\Models\Symptom;
use App\Models\Plant;
use App\Models\CertaintyFactorLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class KnowledgeBaseController extends Controller
{
    /**
     * Tambahkan baris disease_symptoms yang hilang: semua gejala dengan plant_id
     * sama seperti penyakit, dengan CF awal 0 (idempotent).
     *
     * Mencegah: API/client mengirim daftar gejala parsial → detach menghapus pivot
     *           padahal gejala tanaman lain tetap harus ada.
     */
    private function ensureDiseaseLinkedToPlantSymptoms(Disease $disease): void
    {
        if ($disease->plant_id === null) {
            return;
        }

        $linkedIds = $disease->symptoms()->pluck('symptoms.id');
        $missingIds = Symptom::query()
            ->where('plant_id', $disease->plant_id)
            ->whereNotIn('id', $linkedIds)
            ->pluck('id');

        if ($missingIds->isEmpty()) {
            return;
        }

        $attach = [];
        foreach ($missingIds as $symptomId) {
            $attach[$symptomId] = ['certainty_factor' => 0.00];
        }
        $disease->symptoms()->attach($attach);
    }

    /**
     * Mendapatkan semua penyakit dengan gejala (untuk admin panel)
     */
    public function getDiseases(Request $request)
    {
        $plantId = $request->query('plant_id');
        
        $query = Disease::with(['symptoms', 'plant']);
        
        if ($plantId) {
            $query->where('plant_id', $plantId);
        }
        
        $diseases = $query->get();
        
        // Format data untuk admin panel dengan data lengkap gejala
        $formatted = $diseases->map(function($disease) {
            return [
                'id' => $disease->id,
                'name' => $disease->name,
                'code' => $disease->code,
                'description' => $disease->description,
                'cause' => $disease->cause,
                'solution' => $disease->solution,
                'prevention' => $disease->prevention,
                'plant_id' => $disease->plant_id,
                'plant' => $disease->plant ? [
                    'id' => $disease->plant->id,
                    'name' => $disease->plant->name,
                ] : null,
                'symptoms' => $disease->symptoms->map(function($symptom) {
                    return [
                        'id' => $symptom->id,
                        'code' => $symptom->code,
                        'description' => $symptom->description,
                        'pivot' => [
                            'certainty_factor' => (float) $symptom->pivot->certainty_factor,
                        ],
                    ];
                })->toArray(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Mendapatkan penyakit berdasarkan plant_id (untuk Python engine)
     */
    public function getDiseasesByPlant($plantId)
    {
        $diseases = Disease::where('plant_id', $plantId)
            ->with(['symptoms'])
            ->get();
        
        $formatted = $diseases->map(function($disease) {
            return [
                'id' => $disease->id,
                'name' => $disease->name,
                'code' => $disease->code,
                'description' => $disease->description,
                'cause' => $disease->cause,
                'solution' => $disease->solution,
                'prevention' => $disease->prevention,
                'plant_id' => $disease->plant_id,
                'symptoms' => $disease->symptoms->map(function($symptom) {
                    return [
                        'symptom_id' => $symptom->id,
                        'symptom_code' => $symptom->code,
                        'certainty_factor' => (float) $symptom->pivot->certainty_factor,
                    ];
                })->toArray(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Create atau update penyakit
     */
    public function storeDisease(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:diseases,code',
            'description' => 'nullable|string',
            'plant_id' => 'required|exists:plants,id',
            'cause' => 'nullable|string',
            'solution' => 'nullable|string',
            'prevention' => 'nullable|string',
            'symptoms' => 'nullable|array',
            'symptoms.*.symptom_id' => 'required|exists:symptoms,id',
            'symptoms.*.certainty_factor' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $diseaseData = $request->only([
            'name', 'code', 'description', 'plant_id', 'cause', 'solution', 'prevention'
        ]);
        // Kolom description di DB masih NOT NULL, jadi kosongkan jadi string agar tidak error.
        $diseaseData['description'] = $diseaseData['description'] ?? '';

        $disease = Disease::create($diseaseData);

        // Attach symptoms dengan CF jika ada
        if ($request->has('symptoms') && is_array($request->symptoms) && count($request->symptoms) > 0) {
            foreach ($request->symptoms as $symptomData) {
                $disease->symptoms()->attach($symptomData['symptom_id'], [
                    'certainty_factor' => $symptomData['certainty_factor']
                ]);
            }
        }

        $this->ensureDiseaseLinkedToPlantSymptoms($disease);

        $disease->load('symptoms');

        return response()->json([
            'success' => true,
            'message' => 'Penyakit berhasil ditambahkan',
            'data' => $disease
        ], 201);
    }

    /**
     * Get disease detail
     */
    public function show($id)
    {
        $disease = Disease::with(['symptoms', 'plant'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $disease]);
    }

    /**
     * Update penyakit
     */
    public function updateDisease(Request $request, $id)
    {
        $disease = Disease::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|unique:diseases,code,' . $id,
            'description' => 'nullable|string',
            'plant_id' => 'required|exists:plants,id',
            'cause' => 'nullable|string',
            'solution' => 'nullable|string',
            'prevention' => 'nullable|string',
            'symptoms' => 'sometimes|array',
            'symptoms.*.symptom_id' => 'required|exists:symptoms,id',
            'symptoms.*.certainty_factor' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $diseaseData = $request->only([
            'name', 'code', 'description', 'plant_id', 'cause', 'solution', 'prevention'
        ]);
        if (!array_key_exists('description', $diseaseData) || $diseaseData['description'] === null) {
            $diseaseData['description'] = '';
        }

        $incomingPlantId = isset($diseaseData['plant_id']) ? (int) $diseaseData['plant_id'] : (int) $disease->plant_id;
        if ($incomingPlantId !== (int) $disease->plant_id && $disease->symptoms()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tanaman pada penyakit tidak dapat diubah karena sudah ada aturan gejala (CF). Buat penyakit baru untuk tanaman lain, atau hapus dulu hubungan gejala pada penyakit ini.',
            ], 422);
        }

        $disease->update($diseaseData);
        $disease->refresh();

        // Update symptoms jika ada
        if ($request->has('symptoms') && is_array($request->symptoms) && count($request->symptoms) > 0) {
            $disease->symptoms()->detach();
            foreach ($request->symptoms as $symptomData) {
                $disease->symptoms()->attach($symptomData['symptom_id'], [
                    'certainty_factor' => $symptomData['certainty_factor']
                ]);
            }
        }

        // Pulihkan semua gejala bertanda tanaman ini (mencegah payload gejala parsial menghapus pivot permanen)
        $this->ensureDiseaseLinkedToPlantSymptoms($disease);

        $disease->load('symptoms');

        return response()->json([
            'success' => true,
            'message' => 'Penyakit berhasil diupdate',
            'data' => $disease
        ]);
    }

    /**
     * Delete disease
     */
    public function destroy($id)
    {
        $disease = Disease::findOrFail($id);
        $disease->symptoms()->detach();
        $disease->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Penyakit berhasil dihapus'
        ]);
    }

    /**
     * CRUD untuk Plants
     */
    public function getPlants()
    {
        $plants = Plant::all();
        $plants->transform(function($plant) {
            $plantData = $plant->toArray();
            
            // Convert image path to URL
            if (!empty($plantData['image'] ?? null)) {
                if (!str_starts_with($plantData['image'], 'http')) {
                    // Return relative URL: /storage/{file}
                    $plantData['image'] = '/storage/' . ltrim($plantData['image'], '/');
                }
            }
            
            return $plantData;
        });
        
        return response()->json(['success' => true, 'data' => $plants]);
    }

    public function showPlant($id)
    {
        $plant = Plant::findOrFail($id);

        $plantData = $plant->toArray();
        if (!empty($plantData['image'] ?? null) && !str_starts_with($plantData['image'], 'http')) {
            // Return relative URL: /storage/{file}
            $plantData['image'] = '/storage/' . ltrim($plantData['image'], '/');
        }
        
        return response()->json(['success' => true, 'data' => $plantData]);
    }

    public function storePlant(Request $request)
    {
        try {
            Log::info('=== storePlant called ===');
            Log::info('Request method:', ['method' => $request->method()]);
            Log::info('Request content type:', ['content_type' => $request->header('Content-Type')]);
            Log::info('Request all data:', $request->all());
            Log::info('Has file image:', ['has_file' => $request->hasFile('image')]);
            
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                Log::info('File details:', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError()
                ]);
            } else {
                Log::warning('No image file in request');
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'scientific_name' => 'nullable|string',
                'description' => 'nullable|string',
                'care_guide' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB
            ]);

            if ($validator->fails()) {
                Log::warning('Plant validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $plantData = $request->only(['name', 'scientific_name', 'description', 'care_guide']);
            Log::info('Plant data before image:', $plantData);

            // Handle image upload
            if ($request->hasFile('image')) {
                try {
                    $file = $request->file('image');
                    Log::info('Uploading plant image', [
                        'filename' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType()
                    ]);
                    
                    $path = $file->store('plants', 'public');
                    $plantData['image'] = $path;
                    
                    Log::info('Plant image uploaded successfully', ['path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error uploading plant image', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal mengupload gambar: ' . $e->getMessage()
                    ], 500);
                }
            } else {
                Log::info('No image file to upload');
            }

            Log::info('Creating plant with data:', $plantData);
            $plant = Plant::create($plantData);
            
            $plantData = $plant->toArray();
            if (!empty($plantData['image'] ?? null) && !str_starts_with($plantData['image'], 'http')) {
                // Return relative URL: /storage/{file}
                $plantData['image'] = '/storage/' . ltrim($plantData['image'], '/');
            }
            
            Log::info('Plant created successfully', [
                'plant_id' => $plant->id,
                'plant_data' => $plantData
            ]);
            
            return response()->json(['success' => true, 'data' => $plantData], 201);
        } catch (\Exception $e) {
            Log::error('Error creating plant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan tanaman: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePlant(Request $request, $id)
    {
        try {
            Log::info('=== updatePlant called ===');
            Log::info('Request method:', ['method' => $request->method()]);
            Log::info('Request content type:', ['content_type' => $request->header('Content-Type')]);
            Log::info('Request all data keys:', array_keys($request->all()));
            Log::info('Has file image:', ['has_file' => $request->hasFile('image')]);
            
            $plant = Plant::findOrFail($id);
            Log::info('Current plant image:', ['current_image' => $plant->image]);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'scientific_name' => 'nullable|string',
                'description' => 'nullable|string',
                'care_guide' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB
            ]);

            if ($validator->fails()) {
                Log::warning('Plant update validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $plantData = $request->only(['name', 'scientific_name', 'description', 'care_guide']);
            Log::info('Plant data before image:', $plantData);

            // Handle image upload
            if ($request->hasFile('image')) {
                try {
                    $file = $request->file('image');
                    Log::info('File details:', [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError()
                    ]);
                    
                    // Delete old image
                    if ($plant->image && Storage::disk('public')->exists($plant->image)) {
                        Storage::disk('public')->delete($plant->image);
                        Log::info('Old plant image deleted', ['path' => $plant->image]);
                    }

                    Log::info('Uploading plant image for update', [
                        'plant_id' => $id,
                        'filename' => $file->getClientOriginalName(),
                        'size' => $file->getSize()
                    ]);
                    
                    $path = $file->store('plants', 'public');
                    $plantData['image'] = $path;
                    
                    Log::info('Plant image updated successfully', ['path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error uploading plant image for update', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal mengupload gambar: ' . $e->getMessage()
                    ], 500);
                }
            } else {
                Log::warning('No image file in update request');
            }

            Log::info('Updating plant with data:', $plantData);
            $updated = $plant->update($plantData);
            Log::info('Update result:', ['updated' => $updated]);
            
            // Refresh plant to get latest data from database
            $plant = $plant->fresh();
            Log::info('Plant after fresh():', [
                'id' => $plant->id,
                'image' => $plant->image,
                'image_exists' => !empty($plant->image)
            ]);
            
            $plantData = $plant->toArray();
            Log::info('Plant data before URL formatting:', [
                'image' => $plantData['image'] ?? null,
                'all_keys' => array_keys($plantData)
            ]);
            
            if (!empty($plantData['image']) && !str_starts_with($plantData['image'], 'http')) {
                // Return relative URL: /storage/{file}
                $plantData['image'] = '/storage/' . ltrim($plantData['image'], '/');
                Log::info('Image URL formatted:', ['url' => $plantData['image']]);
            } else if (empty($plantData['image'])) {
                Log::warning('Plant image is empty after update!');
            }
            
            Log::info('Plant updated successfully', [
                'plant_id' => $id,
                'plant_data' => $plantData,
                'final_image' => $plantData['image'] ?? 'NULL'
            ]);
            
            return response()->json(['success' => true, 'data' => $plantData]);
        } catch (\Exception $e) {
            Log::error('Error updating plant', [
                'plant_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate tanaman: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyPlant($id)
    {
        $plant = Plant::findOrFail($id);
        
        // Delete image if exists
        if ($plant->image && Storage::disk('public')->exists($plant->image)) {
            Storage::disk('public')->delete($plant->image);
        }

        /*
         * FK diseases.plant_id memakai onDelete('set null') → tanpa ini, penyakit jadi orphan
         * (plant_id null) sementara disease_symptoms masih ada — basis pengetahuan tidak konsisten.
         */
        Disease::where('plant_id', $plant->id)->get()->each(function (Disease $disease) {
            $disease->symptoms()->detach();
            $disease->delete();
        });
        
        $plant->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Tanaman berhasil dihapus'
        ]);
    }

    /**
     * Satu blok matriks CF untuk satu tanaman (gejala terkait x penyakit tanaman).
     */
    private function buildCfMatrixBlock(int $plantId): array
    {
        $plant = Plant::find($plantId);

        $diseases = Disease::with(['symptoms'])
            ->where('plant_id', $plantId)
            ->orderByRaw('LENGTH(code) ASC, code ASC')
            ->get();

        $symptoms = Symptom::query()
            ->orderByRaw('LENGTH(code) ASC, code ASC')
            ->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                    ->orWhereHas('diseases', function ($q2) use ($plantId) {
                        $q2->where('plant_id', $plantId);
                    });
            })
            ->get();

        $matrix = [];

        foreach ($symptoms as $symptom) {
            $row = [
                'symptom_id' => $symptom->id,
                'symptom_code' => $symptom->code,
                'symptom_description' => $symptom->description,
                'diseases' => [],
            ];

            foreach ($diseases as $disease) {
                $cf = 0;
                $relatedSymptom = $disease->symptoms->firstWhere('id', $symptom->id);
                if ($relatedSymptom) {
                    $cf = (float) $relatedSymptom->pivot->certainty_factor;
                }

                $row['diseases'][] = [
                    'disease_id' => $disease->id,
                    'disease_code' => $disease->code,
                    'disease_name' => $disease->name,
                    'certainty_factor' => $cf,
                ];
            }

            $matrix[] = $row;
        }

        return [
            'plant_id' => $plantId,
            'plant_name' => $plant ? $plant->name : '',
            'diseases' => $diseases->map(fn ($d) => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
            ])->values()->all(),
            'matrix' => $matrix,
        ];
    }

    /**
     * Mendapatkan data matriks Certainty Factor.
     * Tanaman tertentu: satu blok. Semua tanaman: satu blok per tanaman (tabel terpisah di UI).
     */
    public function getCFMatrix(Request $request)
    {
        $plantId = $request->query('plant_id');

        if ($plantId) {
            $block = $this->buildCfMatrixBlock((int) $plantId);

            return response()->json([
                'success' => true,
                'data' => [
                    'blocks' => [$block],
                    'symptoms' => collect($block['matrix'])->map(fn ($r) => [
                        'id' => $r['symptom_id'],
                        'code' => $r['symptom_code'],
                        'description' => $r['symptom_description'],
                    ]),
                    'diseases' => $block['diseases'],
                    'matrix' => $block['matrix'],
                ],
            ]);
        }

        $plants = Plant::orderBy('name')->get();
        $blocks = [];
        foreach ($plants as $plant) {
            $blocks[] = $this->buildCfMatrixBlock((int) $plant->id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'blocks' => $blocks,
                'symptoms' => [],
                'diseases' => [],
                'matrix' => [],
            ],
        ]);
    }

    /**
     * Update atau create nilai CF untuk kombinasi symptom-disease
     */
    public function updateCFValue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symptom_id' => 'required|exists:symptoms,id',
            'disease_id' => 'required|exists:diseases,id',
            'certainty_factor' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $symptom = Symptom::findOrFail($request->symptom_id);
        $disease = Disease::findOrFail($request->disease_id);

        if ($symptom->plant_id !== null && (int) $symptom->plant_id !== (int) $disease->plant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Gejala dan penyakit harus untuk tanaman yang sama.',
            ], 422);
        }

        // Check if relation exists
        $exists = $disease->symptoms()->where('symptoms.id', $symptom->id)->exists();

        if ($exists) {
            // Update existing
            $disease->symptoms()->updateExistingPivot($symptom->id, [
                'certainty_factor' => $request->certainty_factor
            ]);
        } else {
            // Create new
            $disease->symptoms()->attach($symptom->id, [
                'certainty_factor' => $request->certainty_factor
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nilai CF berhasil disimpan'
        ]);
    }

    /**
     * CRUD untuk Symptoms
     */
    public function getSymptoms()
    {
        $symptoms = Symptom::orderByRaw('LENGTH(code) ASC, code ASC')->get();
        return response()->json(['success' => true, 'data' => $symptoms]);
    }

    public function showSymptom($id)
    {
        $symptom = Symptom::findOrFail($id);
        return response()->json(['success' => true, 'data' => $symptom]);
    }

    public function storeSymptom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:symptoms,code',
            'description' => 'required|string',
            // Kategori opsional: teks bebas (bukan enum terbatas)
            'category' => 'nullable|string|max:255',
            // Konteks tanaman agar gejala baru bisa langsung dihubungkan
            // ke penyakit yang dimiliki tanaman tersebut.
            'plant_id' => 'nullable|exists:plants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $plantId = $data['plant_id'] ?? null;
        unset($data['plant_id']);

        $cat = $data['category'] ?? null;
        $data['category'] = ($cat !== null && trim((string) $cat) !== '') ? trim((string) $cat) : null;

        $symptom = Symptom::create(array_merge($data, ['plant_id' => $plantId]));

        // Jika plant_id dikirim, kaitkan gejala ke semua penyakit milik plant tsb.
        // CF awal diset ke 0 agar tetap aman (tanpa pengaruh sampai diubah via tabel CF).
        if ($plantId) {
            $diseases = Disease::where('plant_id', $plantId)->get();
            foreach ($diseases as $disease) {
                $disease->symptoms()->syncWithoutDetaching([
                    $symptom->id => ['certainty_factor' => 0.00],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Gejala berhasil ditambahkan',
            'data' => $symptom
        ], 201);
    }

    public function updateSymptom(Request $request, $id)
    {
        $symptom = Symptom::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|unique:symptoms,code,' . $id,
            'description' => 'sometimes|required|string',
            'category' => 'sometimes|nullable|string|max:255',
            'plant_id' => 'sometimes|nullable|exists:plants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (array_key_exists('category', $data)) {
            $cat = $data['category'];
            $data['category'] = ($cat !== null && trim((string) $cat) !== '') ? trim((string) $cat) : null;
        }
        if (array_key_exists('plant_id', $data) && $data['plant_id'] === '') {
            $data['plant_id'] = null;
        }

        $oldPlantId = $symptom->plant_id;
        $symptom->update($data);
        $symptom->refresh();

        if (array_key_exists('plant_id', $data) && $oldPlantId != $symptom->plant_id) {
            if ($symptom->plant_id === null) {
                $symptom->diseases()->detach();
            } else {
                $wrongPlantDiseaseIds = $symptom->diseases()
                    ->where('diseases.plant_id', '!=', $symptom->plant_id)
                    ->pluck('diseases.id');
                if ($wrongPlantDiseaseIds->isNotEmpty()) {
                    $symptom->diseases()->detach($wrongPlantDiseaseIds);
                }
                $diseases = Disease::where('plant_id', $symptom->plant_id)->get();
                foreach ($diseases as $disease) {
                    $disease->symptoms()->syncWithoutDetaching([
                        $symptom->id => ['certainty_factor' => 0.00],
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Gejala berhasil diupdate',
            'data' => $symptom->fresh(['plant']),
        ]);
    }

    public function destroySymptom($id)
    {
        $symptom = Symptom::findOrFail($id);
        $symptom->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Gejala berhasil dihapus'
        ]);
    }

    /**
     * CRUD untuk Certainty Factor Levels
     * Public endpoint: hanya return yang aktif
     */
    public function getCFLevels()
    {
        $levels = CertaintyFactorLevel::where('is_active', true)
            ->orderBy('order')
            ->get();
        
        return response()->json([
            'success' => true, 
            'data' => $levels
        ]);
    }
    
    /**
     * Admin endpoint: return semua (aktif dan tidak aktif)
     */
    public function getAllCFLevels()
    {
        $levels = CertaintyFactorLevel::orderBy('order')->get();
        
        return response()->json([
            'success' => true, 
            'data' => $levels
        ]);
    }

    public function showCFLevel($id)
    {
        $level = CertaintyFactorLevel::findOrFail($id);
        return response()->json(['success' => true, 'data' => $level]);
    }

    public function storeCFLevel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|unique:certainty_factor_levels,label',
            'value' => 'required|numeric|min:0|max:1|unique:certainty_factor_levels,value',
            'order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('certainty_factor_levels', 'order'),
            ],
            'is_active' => 'boolean',
        ], [
            'order.unique' => 'Urutan sudah dipakai oleh level lain. Setiap bobot harus punya urutan berbeda.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $level = CertaintyFactorLevel::create($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Bobot nilai berhasil ditambahkan',
            'data' => $level
        ], 201);
    }

    public function updateCFLevel(Request $request, $id)
    {
        $level = CertaintyFactorLevel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|required|string|unique:certainty_factor_levels,label,' . $id,
            'value' => 'sometimes|required|numeric|min:0|max:1|unique:certainty_factor_levels,value,' . $id,
            'order' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('certainty_factor_levels', 'order')->ignore($id),
            ],
            'is_active' => 'boolean',
        ], [
            'order.unique' => 'Urutan sudah dipakai oleh level lain. Setiap bobot harus punya urutan berbeda.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $level->update($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Bobot nilai berhasil diupdate',
            'data' => $level
        ]);
    }

    public function destroyCFLevel($id)
    {
        $level = CertaintyFactorLevel::findOrFail($id);
        $level->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Bobot nilai berhasil dihapus'
        ]);
    }
}



