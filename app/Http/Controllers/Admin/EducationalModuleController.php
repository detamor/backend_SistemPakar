<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EducationalModuleController extends Controller
{
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

    /**
     * Get all modules
     */
    public function index(Request $request)
    {
        try {
            $query = EducationalModule::query();

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            $modules = $query->orderBy('created_at', 'desc')->paginate(15);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $modules
            ]));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error getting education modules', [
                'error' => $e->getMessage()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil modul edukasi',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Get module detail
     */
    public function show($id)
    {
        try {
            $module = EducationalModule::findOrFail($id);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $module
            ]));
            
            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Modul edukasi tidak ditemukan'
            ], 404));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error getting education module detail', [
                'error' => $e->getMessage(),
                'module_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail modul',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Create module
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'content' => 'required|string',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422));
            
            return $response;
        }

        try {
            $moduleData = $request->only(['title', 'description', 'content', 'category']);

            // Handle image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('educational_modules', 'public');
                $moduleData['image'] = $path;
            }

            $module = EducationalModule::create($moduleData);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Modul edukasi berhasil dibuat',
                'data' => $module
            ], 201));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error creating education module', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat modul edukasi',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Update module
     */
    public function update(Request $request, $id)
    {
        try {
            $module = EducationalModule::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string|max:500',
                'content' => 'sometimes|required|string',
                'category' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422));
                
                return $response;
            }

            $updateData = $request->only(['title', 'description', 'content', 'category', 'is_active']);

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($module->image && Storage::disk('public')->exists($module->image)) {
                    Storage::disk('public')->delete($module->image);
                }

                $path = $request->file('image')->store('educational_modules', 'public');
                $updateData['image'] = $path;
            }

            $module->update($updateData);

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Modul edukasi berhasil diperbarui',
                'data' => $module
            ]));
            
            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Modul edukasi tidak ditemukan'
            ], 404));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error updating education module', [
                'error' => $e->getMessage(),
                'module_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui modul edukasi',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Delete module
     */
    public function destroy($id)
    {
        try {
            $module = EducationalModule::findOrFail($id);

            // Delete image if exists
            if ($module->image && Storage::disk('public')->exists($module->image)) {
                Storage::disk('public')->delete($module->image);
            }

            $module->delete();

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Modul edukasi berhasil dihapus'
            ]));
            
            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Modul edukasi tidak ditemukan'
            ], 404));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error deleting education module', [
                'error' => $e->getMessage(),
                'module_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus modul edukasi',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }
}
