<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Disease;
use App\Models\Symptom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicStatsController extends Controller
{
    /**
     * Ringkasan sistem untuk halaman publik (Home).
     */
    public function summary()
    {
        // Tanaman & gejala yang aktif ditampilkan di proses diagnosis publik
        $plantsCount = Plant::where('is_active', true)->count();
        $symptomsCount = Symptom::where('is_active', true)->count();

        // Penyakit mengikuti data yang digunakan oleh engine (di project ini biasanya tidak difilter is_active)
        $diseasesCount = Disease::count();

        // Jumlah aturan bobot CF = banyaknya tingkat CF aktif (tabel certainty_factor_levels)
        $rulesCount = (int) DB::table('certainty_factor_levels')
            ->where('is_active', true)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'plants' => $plantsCount,
                'diseases' => $diseasesCount,
                'symptoms' => $symptomsCount,
                'rules' => $rulesCount,
            ],
        ]);
    }
}

