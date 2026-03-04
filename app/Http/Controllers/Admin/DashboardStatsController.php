<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use App\Models\ExpertConsultation;
use App\Models\Feedback;
use App\Models\User;
use App\Models\EducationalModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardStatsController extends Controller
{
    public function getQuickStats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'totalUsers' => User::where('role', 'user')->count(),
                'totalDiagnoses' => Diagnosis::count(),
                'totalModules' => EducationalModule::count(),
                'totalConsultations' => ExpertConsultation::count(),
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
            ->groupBy('month')
            ->orderBy('created_at', 'asc')
            ->limit(6)
            ->get();

        // Accuracy Feedback Distribution
        $feedbackStats = Feedback::select('rating', DB::raw('count(*) as total'))
            ->groupBy('rating')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'topDiseases' => $topDiseases,
                'monthlyTrend' => $monthlyTrend,
                'feedbackStats' => $feedbackStats
            ]
        ]);
    }
}
