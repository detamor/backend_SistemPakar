<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disease;
use App\Models\Symptom;
use App\Models\Plant;
use App\Models\CertaintyFactorLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KnowledgeBaseController extends Controller
{
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
            'description' => 'required|string',
            'plant_id' => 'nullable|exists:plants,id',
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

        $disease = Disease::create($request->only([
            'name', 'code', 'description', 'plant_id', 'cause', 'solution', 'prevention'
        ]));

        // Attach symptoms dengan CF jika ada
        if ($request->has('symptoms') && is_array($request->symptoms) && count($request->symptoms) > 0) {
            foreach ($request->symptoms as $symptomData) {
                $disease->symptoms()->attach($symptomData['symptom_id'], [
                    'certainty_factor' => $symptomData['certainty_factor']
                ]);
            }
        }

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
            'description' => 'sometimes|required|string',
            'plant_id' => 'nullable|exists:plants,id',
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

        $disease->update($request->only([
            'name', 'code', 'description', 'plant_id', 'cause', 'solution', 'prevention'
        ]));

        // Update symptoms jika ada
        if ($request->has('symptoms') && is_array($request->symptoms) && count($request->symptoms) > 0) {
            $disease->symptoms()->detach();
            foreach ($request->symptoms as $symptomData) {
                $disease->symptoms()->attach($symptomData['symptom_id'], [
                    'certainty_factor' => $symptomData['certainty_factor']
                ]);
            }
        }

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
        return response()->json(['success' => true, 'data' => $plants]);
    }

    public function showPlant($id)
    {
        $plant = Plant::findOrFail($id);
        return response()->json(['success' => true, 'data' => $plant]);
    }

    public function storePlant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'scientific_name' => 'nullable|string',
            'description' => 'nullable|string',
            'care_guide' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plant = Plant::create($request->all());
        return response()->json(['success' => true, 'data' => $plant], 201);
    }

    public function updatePlant(Request $request, $id)
    {
        $plant = Plant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'scientific_name' => 'nullable|string',
            'description' => 'nullable|string',
            'care_guide' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plant->update($request->all());
        return response()->json(['success' => true, 'data' => $plant]);
    }

    public function destroyPlant($id)
    {
        $plant = Plant::findOrFail($id);
        $plant->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Tanaman berhasil dihapus'
        ]);
    }

    /**
     * Mendapatkan data matriks Certainty Factor (semua gejala x semua penyakit)
     */
    public function getCFMatrix(Request $request)
    {
        $plantId = $request->query('plant_id');
        
        // Get all symptoms
        $symptoms = Symptom::orderBy('code')->get();
        
        // Get all diseases
        $query = Disease::with(['symptoms']);
        if ($plantId) {
            $query->where('plant_id', $plantId);
        }
        $diseases = $query->orderBy('code')->get();
        
        // Build matrix data
        $matrix = [];
        
        foreach ($symptoms as $symptom) {
            $row = [
                'symptom_id' => $symptom->id,
                'symptom_code' => $symptom->code,
                'symptom_description' => $symptom->description,
                'diseases' => []
            ];
            
            foreach ($diseases as $disease) {
                // Find CF for this symptom-disease combination
                $cf = 0;
                $relatedSymptom = $disease->symptoms->firstWhere('id', $symptom->id);
                if ($relatedSymptom) {
                    $cf = (float) $relatedSymptom->pivot->certainty_factor;
                }
                
                $row['diseases'][] = [
                    'disease_id' => $disease->id,
                    'disease_code' => $disease->code,
                    'disease_name' => $disease->name,
                    'certainty_factor' => $cf
                ];
            }
            
            $matrix[] = $row;
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'symptoms' => $symptoms->map(fn($s) => ['id' => $s->id, 'code' => $s->code, 'description' => $s->description]),
                'diseases' => $diseases->map(fn($d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name]),
                'matrix' => $matrix
            ]
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
        $symptoms = Symptom::all();
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
            'category' => 'required|string|in:DAUN,BATANG,AKAR,BUNGA,UMUM',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $symptom = Symptom::create($request->all());
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
            'category' => 'sometimes|required|string|in:DAUN,BATANG,AKAR,BUNGA,UMUM',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $symptom->update($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Gejala berhasil diupdate',
            'data' => $symptom
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
            'order' => 'required|integer|min:1',
            'is_active' => 'boolean',
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
            'order' => 'sometimes|required|integer|min:1',
            'is_active' => 'boolean',
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



