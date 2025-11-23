<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disease;
use App\Models\Symptom;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KnowledgeBaseController extends Controller
{
    /**
     * Mendapatkan semua penyakit dengan gejala
     */
    public function getDiseases(Request $request)
    {
        $plantId = $request->query('plant_id');
        
        $query = Disease::with(['symptoms', 'plant']);
        
        if ($plantId) {
            $query->where('plant_id', $plantId);
        }
        
        $diseases = $query->get();
        
        // Format data untuk Python engine
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
            'symptoms' => 'required|array|min:1',
            'symptoms.*.symptom_id' => 'required|exists:symptoms,id',
            'symptoms.*.certainty_factor' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $disease = Disease::create($request->only([
            'name', 'code', 'description', 'plant_id', 'cause', 'solution', 'prevention'
        ]));

        // Attach symptoms dengan CF
        foreach ($request->symptoms as $symptomData) {
            $disease->symptoms()->attach($symptomData['symptom_id'], [
                'certainty_factor' => $symptomData['certainty_factor']
            ]);
        }

        $disease->load('symptoms');

        return response()->json([
            'success' => true,
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
                'errors' => $validator->errors()
            ], 422);
        }

        $disease->update($request->only([
            'name', 'code', 'description', 'plant_id', 'cause', 'solution', 'prevention'
        ]));

        // Update symptoms jika ada
        if ($request->has('symptoms')) {
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
            'category' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $symptom = Symptom::create($request->all());
        return response()->json(['success' => true, 'data' => $symptom], 201);
    }

    public function updateSymptom(Request $request, $id)
    {
        $symptom = Symptom::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|unique:symptoms,code,' . $id,
            'description' => 'sometimes|required|string',
            'category' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $symptom->update($request->all());
        return response()->json(['success' => true, 'data' => $symptom]);
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
}



