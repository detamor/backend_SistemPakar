<?php

namespace App\Http\Controllers;

use App\Models\EducationalModule;
use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
     * Mendapatkan semua modul edukasi
     */
    public function index(Request $request)
    {
        try {
            $category = $request->query('category');
            $query = EducationalModule::where('is_active', true);
            
            if ($category) {
                $query->where('category', $category);
            }
            
            $modules = $query->orderBy('created_at', 'desc')->paginate(10);
            
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
    public function show($id)
    {
        try {
            $module = EducationalModule::findOrFail($id);
            
            // Increment view count
            $module->increment('view_count');
            
            // Cek apakah user sudah bookmark
            $isBookmarked = false;
            if (auth()->check()) {
                $isBookmarked = Bookmark::where('user_id', auth()->id())
                    ->where('educational_module_id', $id)
                    ->exists();
            }
            
            $this->addCorsHeaders($response = response()->json([
                'success' => true,
                'data' => $module,
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
    public function bookmark($id)
    {
        try {
            $user = auth()->user();
            
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
    public function unbookmark($id)
    {
        try {
            $user = auth()->user();
            
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
    public function getBookmarks()
    {
        try {
            $user = auth()->user();
            
            $bookmarks = Bookmark::where('user_id', $user->id)
                ->with('educationalModule')
                ->orderBy('created_at', 'desc')
                ->get();
            
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



