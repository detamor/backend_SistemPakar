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
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        
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

            // Format image URLs for each module
            $appUrl = env('APP_URL', 'http://localhost:8000');
            // Ensure port is included for localhost
            if (str_contains($appUrl, 'localhost') && !str_contains($appUrl, 'localhost:')) {
                $appUrl = str_replace('localhost', 'localhost:8000', $appUrl);
            }
            $modules->getCollection()->transform(function($module) use ($appUrl) {
                $moduleData = $module->toArray();
                
                // Convert thumbnail image path to URL
                if ($moduleData['image']) {
                    if (!str_starts_with($moduleData['image'], 'http')) {
                        $moduleData['image'] = $appUrl . '/storage/' . ltrim($moduleData['image'], '/');
                    }
                }
                
                // Convert content_images paths to URLs
                if (!empty($moduleData['content_images']) && is_array($moduleData['content_images'])) {
                    $moduleData['content_images'] = array_map(function($path) use ($appUrl) {
                        if ($path && !str_starts_with($path, 'http')) {
                            return $appUrl . '/storage/' . ltrim($path, '/');
                        }
                        return $path;
                    }, $moduleData['content_images']);
                }
                
                return $moduleData;
            });

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
            
            // Format image URLs
            $moduleData = $module->toArray();
            $appUrl = env('APP_URL', 'http://localhost:8000');
            // Ensure port is included for localhost
            if (str_contains($appUrl, 'localhost') && !str_contains($appUrl, 'localhost:')) {
                $appUrl = str_replace('localhost', 'localhost:8000', $appUrl);
            }
            
            // Convert thumbnail image path to URL
            if ($moduleData['image']) {
                if (!str_starts_with($moduleData['image'], 'http')) {
                    $moduleData['image'] = $appUrl . '/storage/' . ltrim($moduleData['image'], '/');
                }
            }
            
            // Convert content_images paths to URLs
            if (!empty($moduleData['content_images']) && is_array($moduleData['content_images'])) {
                $moduleData['content_images'] = array_map(function($path) use ($appUrl) {
                    if ($path && !str_starts_with($path, 'http')) {
                        return $appUrl . '/storage/' . ltrim($path, '/');
                    }
                    return $path;
                }, $moduleData['content_images']);
            }

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $moduleData
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
     * Upload content image
     */
    public function uploadContentImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422));
            
            return $response;
        }

        try {
            $path = $request->file('image')->store('educational_modules/content', 'public');
            $appUrl = env('APP_URL', 'http://localhost:8000');
            // Ensure port is included for localhost
            if (str_contains($appUrl, 'localhost') && !str_contains($appUrl, 'localhost:')) {
                $appUrl = str_replace('localhost', 'localhost:8000', $appUrl);
            }
            $url = $appUrl . '/storage/' . ltrim($path, '/');

            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => [
                    'path' => $path,
                    'url' => $url
                ]
            ]));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error uploading content image', [
                'error' => $e->getMessage()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Gagal mengupload gambar',
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
            'content_images' => 'nullable|array',
            'content_images.*' => 'string',
            'is_maintenance_guide' => 'nullable|boolean',
            'watering_info' => 'nullable|string|max:255',
            'light_info' => 'nullable|string|max:255',
            'humidity_info' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:Mudah,Sedang,Sulit',
            'maintenance_steps_json' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422));
            
            return $response;
        }

        try {
            $moduleData = $request->only([
                'title', 'description', 'content', 'category', 
                'is_maintenance_guide', 'watering_info', 'light_info', 
                'humidity_info', 'difficulty', 'maintenance_steps_json'
            ]);

            // Handle thumbnail image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('educational_modules', 'public');
                $moduleData['image'] = $path;
            }

            // Handle content images (array of paths/URLs)
            if ($request->has('content_images')) {
                $moduleData['content_images'] = $request->content_images;
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
                'content_images' => 'nullable|array',
                'content_images.*' => 'string',
                'is_active' => 'sometimes|boolean',
                'is_maintenance_guide' => 'nullable|boolean',
                'watering_info' => 'nullable|string|max:255',
                'light_info' => 'nullable|string|max:255',
                'humidity_info' => 'nullable|string|max:255',
                'difficulty' => 'nullable|in:Mudah,Sedang,Sulit',
                'maintenance_steps_json' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                $this->addCorsHeaders($response = response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422));
                
                return $response;
            }

            $updateData = $request->only([
                'title', 'description', 'content', 'category', 'is_active',
                'is_maintenance_guide', 'watering_info', 'light_info', 
                'humidity_info', 'difficulty', 'maintenance_steps_json'
            ]);

            // Handle thumbnail image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($module->image && Storage::disk('public')->exists($module->image)) {
                    Storage::disk('public')->delete($module->image);
                }

                $path = $request->file('image')->store('educational_modules', 'public');
                $updateData['image'] = $path;
            }

            // Handle content images
            if ($request->has('content_images')) {
                // Delete old content images that are not in the new list
                $oldImages = $module->content_images ?? [];
                $newImages = $request->content_images ?? [];
                
                foreach ($oldImages as $oldImage) {
                    if (!in_array($oldImage, $newImages) && Storage::disk('public')->exists($oldImage)) {
                        Storage::disk('public')->delete($oldImage);
                    }
                }
                
                $updateData['content_images'] = $newImages;
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

            // Delete thumbnail image if exists
            if ($module->image && Storage::disk('public')->exists($module->image)) {
                Storage::disk('public')->delete($module->image);
            }

            // Delete content images if exists
            if ($module->content_images && is_array($module->content_images)) {
                foreach ($module->content_images as $contentImage) {
                    if (Storage::disk('public')->exists($contentImage)) {
                        Storage::disk('public')->delete($contentImage);
                    }
                }
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
