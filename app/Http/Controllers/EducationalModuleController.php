<?php

namespace App\Http\Controllers;

use App\Models\EducationalModule;
use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EducationalModuleController extends Controller
{
    /**
     * Mendapatkan semua modul edukasi
     */
    public function index(Request $request)
    {
        $category = $request->query('category');
        $query = EducationalModule::where('is_active', true);
        
        if ($category) {
            $query->where('category', $category);
        }
        
        $modules = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * Mendapatkan detail modul edukasi
     */
    public function show($id)
    {
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
        
        return response()->json([
            'success' => true,
            'data' => $module,
            'is_bookmarked' => $isBookmarked
        ]);
    }

    /**
     * Bookmark modul edukasi
     */
    public function bookmark($id)
    {
        $user = auth()->user();
        
        $bookmark = Bookmark::firstOrCreate([
            'user_id' => $user->id,
            'educational_module_id' => $id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Modul berhasil di-bookmark',
            'data' => $bookmark
        ]);
    }

    /**
     * Unbookmark modul edukasi
     */
    public function unbookmark($id)
    {
        $user = auth()->user();
        
        Bookmark::where('user_id', $user->id)
            ->where('educational_module_id', $id)
            ->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Bookmark berhasil dihapus'
        ]);
    }

    /**
     * Mendapatkan bookmark user
     */
    public function getBookmarks()
    {
        $user = auth()->user();
        
        $bookmarks = Bookmark::where('user_id', $user->id)
            ->with('educationalModule')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $bookmarks
        ]);
    }
}



