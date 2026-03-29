<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    /**
     * Get all users
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        $role = $request->query('role');
        if (is_string($role) && $role !== '') {
            $query->where('role', $role);
        }

        // Search
        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get user detail
     */
    public function show(int $id)
    {
        $user = User::with(['diagnoses', 'bookmarks', 'consultations'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Create user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:user,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make((string) $request->input('password')),
            'role' => $request->input('role'),
            'is_verified' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => $user
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|required|in:user,admin',
            'is_verified' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['name', 'email', 'role', 'is_verified']);

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make((string) $request->input('password'));
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui',
            'data' => $user
        ]);
    }

    /**
     * Delete user
     */
    public function destroy(Request $request, int $id)
    {
        $authUser = $request->user();
        if (! $authUser instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ((int) $user->id === (int) $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ]);
    }
}
