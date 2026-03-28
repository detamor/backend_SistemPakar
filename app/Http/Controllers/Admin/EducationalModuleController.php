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
    protected function normalizeTagTokens(array $tokens): array
    {
        $normalized = [];
        $seen = [];

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                continue;
            }
            $parts = preg_split('/[,\s]+/', trim($token)) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $tag = str_starts_with($part, '#') ? $part : ('#' . $part);
                $key = mb_strtolower($tag);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $normalized[] = $tag;
                }
            }
        }

        return $normalized;
    }

    protected function normalizeMaintenanceTagsInput(mixed $input): array
    {
        if (is_array($input)) {
            return $this->normalizeTagTokens($input);
        }
        if (is_string($input)) {
            return $this->normalizeTagTokens([$input]);
        }
        return [];
    }

    /**
     * Bangun URL publik file storage berdasarkan host request aktif.
     */
    protected function buildPublicFileUrl(Request $request, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if ($parsedPath && str_contains($parsedPath, '/storage/')) {
                return rtrim($request->getSchemeAndHttpHost(), '/') . $parsedPath;
            }
            return $path;
        }

        $normalizedPath = $this->normalizeStoredPath($path);
        $storagePath = Storage::url(ltrim((string) $normalizedPath, '/')); // /storage/xxx
        return rtrim($request->getSchemeAndHttpHost(), '/') . $storagePath;
    }

    /**
     * Normalisasi path file agar yang tersimpan konsisten relatif ke storage/public.
     */
    protected function normalizeStoredPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $clean = trim($path);
        if ($clean === '') {
            return null;
        }

        // Jika URL absolut local storage, ubah jadi path relatif.
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            $parsedPath = parse_url($clean, PHP_URL_PATH);
            if ($parsedPath && str_contains($parsedPath, '/storage/')) {
                $clean = substr($parsedPath, strpos($parsedPath, '/storage/') + strlen('/storage/'));
            } else {
                // URL eksternal dibiarkan apa adanya.
                return $clean;
            }
        }

        $clean = ltrim($clean, '/');
        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/'));
        }

        return $clean;
    }

    protected function normalizeStoredPaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }
            $value = $this->normalizeStoredPath($path);
            if ($value && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }
        return $normalized;
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
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            $modules = $query->orderBy('created_at', 'desc')->paginate(15);

            // Format image URLs for each module
            $modules->getCollection()->transform(function($module) use ($request) {
                $moduleData = $module->toArray();
                
                // Convert thumbnail image path to URL
                if ($moduleData['image']) {
                    $moduleData['image'] = $this->buildPublicFileUrl($request, $moduleData['image']);
                }
                
                // Convert content_images paths to URLs
                if (!empty($moduleData['content_images']) && is_array($moduleData['content_images'])) {
                    $moduleData['content_images'] = array_map(function($path) use ($request) {
                        return $this->buildPublicFileUrl($request, $path);
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
    public function show(Request $request, $id)
    {
        try {
            $module = EducationalModule::findOrFail($id);
            
            // Format image URLs
            $moduleData = $module->toArray();
            
            // Convert thumbnail image path to URL
            if ($moduleData['image']) {
                $moduleData['image'] = $this->buildPublicFileUrl($request, $moduleData['image']);
            }
            
            // Convert content_images paths to URLs
            if (!empty($moduleData['content_images']) && is_array($moduleData['content_images'])) {
                $moduleData['content_images'] = array_map(function($path) use ($request) {
                    return $this->buildPublicFileUrl($request, $path);
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
            $url = $this->buildPublicFileUrl($request, $path);

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
            'content' => 'required|string',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'content_images' => 'nullable|array',
            'content_images.*' => 'string',
            'is_maintenance_guide' => 'nullable|boolean',
            'vital_tags_json' => 'nullable|array',
            'vital_tags_json.*' => 'string|max:50',
            'maintenance_tags' => 'nullable|string|max:500',
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
                'title', 'content', 'category',
                'is_maintenance_guide', 'maintenance_steps_json'
            ]);

            $normalizedTags = $this->normalizeMaintenanceTagsInput(
                $request->input('vital_tags_json', $request->input('maintenance_tags'))
            );
            $moduleData['vital_tags_json'] = !empty($normalizedTags) ? $normalizedTags : null;
            // Hard stop: legacy fields are no longer used.
            $moduleData['watering_info'] = null;
            $moduleData['light_info'] = null;
            $moduleData['humidity_info'] = null;
            $moduleData['difficulty'] = null;

            // Handle thumbnail image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('educational_modules', 'public');
                $moduleData['image'] = $path;
            }

            // Handle content images (array of paths/URLs)
            if ($request->has('content_images')) {
                $moduleData['content_images'] = $this->normalizeStoredPaths($request->content_images ?? []);
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
                'content' => 'sometimes|required|string',
                'category' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'content_images' => 'nullable|array',
                'content_images.*' => 'string',
                'is_active' => 'sometimes|boolean',
                'is_maintenance_guide' => 'nullable|boolean',
                'vital_tags_json' => 'nullable|array',
                'vital_tags_json.*' => 'string|max:50',
                'maintenance_tags' => 'nullable|string|max:500',
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
                'title', 'content', 'category', 'is_active',
                'is_maintenance_guide', 'maintenance_steps_json'
            ]);

            if ($request->has('vital_tags_json') || $request->has('maintenance_tags')) {
                $normalizedTags = $this->normalizeMaintenanceTagsInput(
                    $request->input('vital_tags_json', $request->input('maintenance_tags'))
                );
                $updateData['vital_tags_json'] = !empty($normalizedTags) ? $normalizedTags : null;
            }

            // Hard stop: always clear legacy fields on update.
            $updateData['watering_info'] = null;
            $updateData['light_info'] = null;
            $updateData['humidity_info'] = null;
            $updateData['difficulty'] = null;

            // Handle thumbnail image upload
            if ($request->hasFile('image')) {
                // Delete old image
                $oldImage = $this->normalizeStoredPath($module->image);
                $isExternalOldImage = $oldImage
                    && (str_starts_with($oldImage, 'http://') || str_starts_with($oldImage, 'https://'));
                if ($oldImage && !$isExternalOldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }

                $path = $request->file('image')->store('educational_modules', 'public');
                $updateData['image'] = $path;
            }

            // Handle content images
            if ($request->has('content_images')) {
                // Delete old content images that are not in the new list
                $oldImages = $this->normalizeStoredPaths($module->content_images ?? []);
                $newImages = $this->normalizeStoredPaths($request->content_images ?? []);
                
                foreach ($oldImages as $oldImage) {
                    $isExternal = str_starts_with($oldImage, 'http://') || str_starts_with($oldImage, 'https://');
                    if (!$isExternal && !in_array($oldImage, $newImages, true) && Storage::disk('public')->exists($oldImage)) {
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
            $normalizedThumbnail = $this->normalizeStoredPath($module->image);
            $isExternalThumbnail = $normalizedThumbnail
                && (str_starts_with($normalizedThumbnail, 'http://') || str_starts_with($normalizedThumbnail, 'https://'));
            if ($normalizedThumbnail && !$isExternalThumbnail && Storage::disk('public')->exists($normalizedThumbnail)) {
                Storage::disk('public')->delete($normalizedThumbnail);
            }

            // Delete content images if exists
            if ($module->content_images && is_array($module->content_images)) {
                foreach ($this->normalizeStoredPaths($module->content_images) as $contentImage) {
                    $isExternal = str_starts_with($contentImage, 'http://') || str_starts_with($contentImage, 'https://');
                    if (!$isExternal && Storage::disk('public')->exists($contentImage)) {
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
