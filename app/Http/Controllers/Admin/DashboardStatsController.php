<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use App\Models\Feedback;
use App\Models\User;
use App\Models\EducationalModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardStatsController extends Controller
{
    public function getQuickStats()
    {
        $totalFeedbackComments = Feedback::query()
            ->whereNotNull('comment')
            ->whereRaw('TRIM(comment) <> ""')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'totalUsers' => User::where('role', 'user')->count(),
                'totalDiagnoses' => Diagnosis::count(),
                'totalModules' => EducationalModule::count(),
                'totalFeedbackComments' => $totalFeedbackComments,
            ]
        ]);
    }

    public function getDiagnosisStats()
    {
        // Top Diseases
        $topDiseases = Diagnosis::select('disease_id', DB::raw('count(*) as total'))
            ->with('disease:id,name')
            ->whereNotNull('disease_id')
            ->groupBy('disease_id')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        // Diagnosis Monthly Trend (last 6 months)
        $monthlyTrend = Diagnosis::select(
            DB::raw('DATE_FORMAT(created_at, "%M %Y") as month'),
            DB::raw('count(*) as total')
        )
            // Untuk kompatibilitas dengan sql_mode=only_full_group_by,
            // orderby tidak boleh memakai created_at langsung karena tidak ada di GROUP BY.
            // Pakai expression yang sama dengan SELECT alias month.
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%M %Y")'))
            ->orderBy('month', 'asc')
            ->limit(6)
            ->get();

        // Accuracy Feedback Distribution (selalu kirim 3 kategori meski nilainya 0)
        $rawFeedbackStats = Feedback::select('accuracy', DB::raw('count(*) as total'))
            ->whereNotNull('accuracy')
            ->groupBy('accuracy')
            ->pluck('total', 'accuracy')
            ->toArray();

        $feedbackStats = collect([
            ['accuracy' => 'accurate', 'total' => (int) ($rawFeedbackStats['accurate'] ?? 0)],
            ['accuracy' => 'somewhat_accurate', 'total' => (int) ($rawFeedbackStats['somewhat_accurate'] ?? 0)],
            ['accuracy' => 'inaccurate', 'total' => (int) ($rawFeedbackStats['inaccurate'] ?? 0)],
        ]);

        // Komentar feedback terbaru untuk ditampilkan di dashboard admin.
        $recentFeedbacks = Feedback::query()
            ->with([
                'user:id,name',
                'diagnosis:id,disease_id,plant_id',
                'diagnosis.disease:id,name',
                'diagnosis.plant:id,name',
            ])
            ->whereNotNull('comment')
            ->whereRaw('TRIM(comment) <> ""')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'accuracy' => $feedback->accuracy,
                    'comment' => trim((string) $feedback->comment),
                    'created_at' => optional($feedback->created_at)->toISOString(),
                    'user_name' => $feedback->user?->name ?? 'User',
                    'plant_name' => $feedback->diagnosis?->plant?->name,
                    'disease_name' => $feedback->diagnosis?->disease?->name,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'topDiseases' => $topDiseases,
                'monthlyTrend' => $monthlyTrend,
                'feedbackStats' => $feedbackStats,
                'recentFeedbacks' => $recentFeedbacks,
            ]
        ]);
    }
}
