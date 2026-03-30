<?php

namespace App\Http\Controllers;

use App\Models\EducationalModule;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EducationalModuleController extends Controller
{
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

        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . Storage::url($cleanPath);
    }

    protected function formatModuleForResponse(Request $request, EducationalModule $module): array
    {
        $moduleData = $module->toArray();

        if (!empty($moduleData['image'])) {
            $moduleData['image'] = $this->buildPublicFileUrl($request, $moduleData['image']);
        }

        if (!empty($moduleData['content_images']) && is_array($moduleData['content_images'])) {
            $moduleData['content_images'] = array_map(function ($path) use ($request) {
                return $this->buildPublicFileUrl($request, $path);
            }, $moduleData['content_images']);
        }

        return $moduleData;
    }

    /**
     * Escape %, _, \ untuk digunakan di pola LIKE.
     */
    protected function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Pola LIKE case-insensitive: nilai user di-lower, kolom dibandingkan dengan LOWER(...).
     */
    protected function caseInsensitiveLikePattern(string $search): string
    {
        return '%'.$this->escapeLikePattern(mb_strtolower($search, 'UTF-8')).'%';
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
     * Mendapatkan semua modul edukasi
     */
    public function index(Request $request)
    {
        try {
            $plantId = $request->query('plant_id');
            $includeGeneralRaw = $request->query('include_general', '1');
            $includeGeneral = !in_array(mb_strtolower((string) $includeGeneralRaw), ['0', 'false', 'no'], true);
            $search = trim((string) $request->query('search', ''));
            $query = EducationalModule::with('plant')->where('is_active', true);
            
            if (is_numeric($plantId)) {
                $id = (int) $plantId;
                if ($includeGeneral) {
                    $query->where(function ($q) use ($id) {
                        $q->where('plant_id', $id)->orWhereNull('plant_id');
                    });
                } else {
                    $query->where('plant_id', $id);
                }
            }

            if ($search !== '') {
                $normalizedSearch = ltrim($search, '#');
                $pat = $this->caseInsensitiveLikePattern($search);
                $patNorm = $this->caseInsensitiveLikePattern($normalizedSearch);
                $patHashNorm = $this->caseInsensitiveLikePattern('#'.$normalizedSearch);

                $query->where(function ($q) use ($pat, $patNorm, $patHashNorm, $search, $normalizedSearch) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$pat])
                        ->orWhereRaw('LOWER(COALESCE(content, "")) LIKE ?', [$pat])
                        ->orWhereHas('plant', function ($pq) use ($pat) {
                            $pq->whereRaw('LOWER(name) LIKE ?', [$pat]);
                        })
                        ->orWhereRaw('LOWER(CAST(vital_tags_json AS CHAR)) LIKE ?', [$pat]);

                    if ($normalizedSearch !== $search) {
                        $q->orWhereRaw('LOWER(CAST(vital_tags_json AS CHAR)) LIKE ?', [$patNorm]);
                    } else {
                        $q->orWhereRaw('LOWER(CAST(vital_tags_json AS CHAR)) LIKE ?', [$patHashNorm]);
                    }
                });
            }
            
            $modules = $query->orderBy('created_at', 'desc')->paginate(10);
            $modules->getCollection()->transform(function ($module) use ($request) {
                return $this->formatModuleForResponse($request, $module);
            });
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $modules
            ]));
            
            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting education modules', [
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
     * Mendapatkan detail modul edukasi
     */
    public function show(Request $request, $id)
    {
        try {
            $module = EducationalModule::with('plant')->findOrFail($id);
            
            // Cek apakah user sudah bookmark
            $isBookmarked = false;
            $user = Auth::guard('sanctum')->user();
            if (! $user instanceof User) {
                $user = $request->user();
            }
            if ($user instanceof User) {
                $isBookmarked = Bookmark::where('user_id', $user->id)
                    ->where('educational_module_id', $id)
                    ->exists();
            }
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $this->formatModuleForResponse($request, $module),
                'is_bookmarked' => $isBookmarked
            ]));
            
            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Modul edukasi tidak ditemukan'
            ], 404));
            
            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting education module detail', [
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
     * Bookmark modul edukasi
     */
    public function bookmark(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $bookmark = Bookmark::firstOrCreate([
                'user_id' => $user->id,
                'educational_module_id' => $id
            ]);
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Modul berhasil di-bookmark',
                'data' => $bookmark
            ]));
            
            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error bookmarking module', [
                'error' => $e->getMessage(),
                'module_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat bookmark modul',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Unbookmark modul edukasi
     */
    public function unbookmark(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            Bookmark::where('user_id', $user->id)
                ->where('educational_module_id', $id)
                ->delete();
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'message' => 'Bookmark berhasil dihapus'
            ]));
            
            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error unbookmarking module', [
                'error' => $e->getMessage(),
                'module_id' => $id
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus bookmark',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }

    /**
     * Mendapatkan bookmark user
     */
    public function getBookmarks(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $bookmarks = Bookmark::where('user_id', $user->id)
                ->with('educationalModule.plant')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($bookmark) use ($request) {
                    $bookmarkData = $bookmark->toArray();
                    if ($bookmark->educationalModule) {
                        $bookmarkData['educational_module'] = $this->formatModuleForResponse($request, $bookmark->educationalModule);
                    }
                    return $bookmarkData;
                })
                ->values();
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $bookmarks
            ]));
            
            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting bookmarks', [
                'error' => $e->getMessage()
            ]);

            $this->addCorsHeaders($response = response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil bookmark',
                'error' => $e->getMessage()
            ], 500));
            
            return $response;
        }
    }
}
