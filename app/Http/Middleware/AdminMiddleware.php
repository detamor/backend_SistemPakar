<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Middleware admin untuk API Sanctum:
        // - Gunakan $request->user() (hasil auth:sanctum) agar konsisten untuk SPA + Bearer token.
        // - Jangan pakai auth()->check() (guard web) karena bisa membuat admin dianggap tidak login.
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        return $next($request);
    }
}

