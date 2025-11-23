<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EducationalModuleController extends Controller
{
    /**
     * Get all modules
     */
    public function index(Request $request)
    {
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

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * Get module detail
     */
    public function show($id)
    {
        $module = EducationalModule::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $module
        ]);
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $moduleData = $request->only(['title', 'content', 'category']);

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('educational_modules', 'public');
            $moduleData['image'] = $path;
        }

        $module = EducationalModule::create($moduleData);

        return response()->json([
            'success' => true,
            'message' => 'Modul edukasi berhasil dibuat',
            'data' => $module
        ], 201);
    }

    /**
     * Update module
     */
    public function update(Request $request, $id)
    {
        $module = EducationalModule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['title', 'content', 'category', 'is_active']);

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

        return response()->json([
            'success' => true,
            'message' => 'Modul edukasi berhasil diperbarui',
            'data' => $module
        ]);
    }

    /**
     * Delete module
     */
    public function destroy($id)
    {
        $module = EducationalModule::findOrFail($id);

        // Delete image if exists
        if ($module->image && Storage::disk('public')->exists($module->image)) {
            Storage::disk('public')->delete($module->image);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Modul edukasi berhasil dihapus'
        ]);
    }
}


