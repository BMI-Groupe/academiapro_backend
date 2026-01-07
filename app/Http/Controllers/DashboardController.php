<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Section;
use App\Models\SchoolYear;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Http\Resources\SchoolYearResource;
use App\Http\Resources\ScheduleResource;
use Illuminate\Http\Request;
use App\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $schoolYearId = $request->query('school_year_id');
        
        // Si aucune année définie, prendre l'active ou la dernière
        if (!$schoolYearId || $schoolYearId === 'undefined') {
            $activeYear = SchoolYear::where('is_active', true)->first();
            $schoolYearId = $activeYear ? $activeYear->id : SchoolYear::max('id');
        }

        $schoolYear = SchoolYear::find($schoolYearId);
        
        if (!$schoolYear) {
            return ApiResponse::sendResponse(false, null, 'Année scolaire introuvable', 404);
        }

        // 1. Compteurs globaux pour l'année sélectionnée
        $counts = [
            'students' => Enrollment::where('school_year_id', $schoolYearId)->count(),
            'classes' => Section::where('school_year_id', $schoolYearId)->count(),
            'teachers' => Teacher::count(), // Total global des enseignants
        ];

        // 2. Inscriptions récentes pour l'année
        $recentEnrollments = Enrollment::where('school_year_id', $schoolYearId)
            ->with(['student', 'section.classroomTemplate'])
            ->orderBy('enrolled_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($enr) {
                $sectionName = $enr->section->display_name ?? $enr->section->name ?? $enr->section->classroomTemplate->name ?? 'N/A';
                return [
                    'id' => $enr->student->id,
                    'first_name' => $enr->student->first_name,
                    'last_name' => $enr->student->last_name,
                    'matricule' => $enr->student->matricule,
                    'classroom' => $sectionName,
                    'label' => $sectionName, // Pour compatibilité
                    'created_at' => $enr->enrolled_at ? Carbon::parse($enr->enrolled_at)->toIso8601String() : null
                ];
            });

        // 3. Graphiques séparés
        $months = [];
        $enrollmentSeries = [];
        $paymentSeries = [];
        
        $start = Carbon::parse($schoolYear->start_date);
        $end = Carbon::parse($schoolYear->end_date);
        
        $period = \Carbon\CarbonPeriod::create($start, '1 month', $end);
        
        foreach ($period as $date) {
            $monthKey = $date->format('Y-m');
            $months[] = $date->locale('fr')->isoFormat('MMM YY'); 
            
            $enrollmentSeries[] = Enrollment::where('school_year_id', $schoolYearId)
                ->whereYear('enrolled_at', $date->year)
                ->whereMonth('enrolled_at', $date->month)
                ->count();
                
            $paymentSeries[] = Payment::where('school_year_id', $schoolYearId)
                ->where('type', 'TUITION')
                ->whereBetween('payment_date', [
                    $date->copy()->startOfMonth(),
                    $date->copy()->endOfMonth()
                ])
                ->sum('amount');
        }

        // Totaux financiers pour l'année sélectionnée
        $totalRevenue = Payment::where('school_year_id', $schoolYearId)
            ->where('type', 'TUITION')
            ->sum('amount') ?? 0;
        
        // Calculer les écolages : total dû, payé, non payé, reste à payer
        $enrollments = Enrollment::where('school_year_id', $schoolYearId)
            ->with('section.classroomTemplate')
            ->get();
        
        $totalDue = $enrollments->sum(function($enrollment) {
            return $enrollment->section ? ($enrollment->section->effective_tuition_fee ?? $enrollment->section->tuition_fee ?? 0) : 0;
        }) ?? 0;
        
        $totalPaid = Payment::where('school_year_id', $schoolYearId)
            ->where('type', 'TUITION')
            ->sum('amount') ?? 0;
        
        $totalUnpaid = $totalDue - $totalPaid;
        $remainingToPay = max(0, $totalUnpaid);
        
        $financialStats = [
            'total_revenue' => (float) $totalRevenue,
            'total_due' => (float) $totalDue,
            'total_paid' => (float) $totalPaid,
            'total_unpaid' => (float) $totalUnpaid,
            'remaining_to_pay' => (float) $remainingToPay,
        ];

        // Statistiques des inscriptions par années (toutes les années)
        // Utiliser withoutGlobalScopes() pour les admins pour voir toutes les années
        $allSchoolYears = SchoolYear::withoutGlobalScopes()
            ->orderBy('start_date', 'desc')
            ->get();
        
        $enrollmentsByYear = [];
        foreach ($allSchoolYears as $year) {
            $enrollmentsByYear[] = [
                'year_id' => $year->id,
                'year_label' => $year->label,
                'start_date' => $year->start_date ? $year->start_date->toDateString() : null,
                'end_date' => $year->end_date ? $year->end_date->toDateString() : null,
                'is_active' => (bool) $year->is_active,
                'enrollment_count' => Enrollment::where('school_year_id', $year->id)->count(),
            ];
        }

        // Chiffre d'affaire par année
        $revenueByYear = [];
        foreach ($allSchoolYears as $year) {
            $yearRevenue = Payment::where('school_year_id', $year->id)
                ->where('type', 'TUITION')
                ->sum('amount') ?? 0;
            
            $revenueByYear[] = [
                'year_id' => $year->id,
                'year_label' => $year->label,
                'revenue' => (float) $yearRevenue,
            ];
        }
        
        // Log pour vérifier
        Log::info('Dashboard - Years data', [
            'all_school_years_count' => $allSchoolYears->count(),
            'enrollments_by_year_count' => count($enrollmentsByYear),
            'revenue_by_year_count' => count($revenueByYear),
        ]);

        // 4. Emplois du temps (prochains cours du jour ou récents)
        // Note: Schedule utilise section qui a school_year_id
        $upcomingSchedules = \App\Models\Schedule::whereHas('section', function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            })
            ->with(['section.classroomTemplate', 'subject', 'teacher.user', 'schoolYear'])
            ->orderBy('created_at', 'desc') // ou logique par jour de la semaine
            ->take(5)
            ->get()
            ->map(fn($schedule) => (new ScheduleResource($schedule))->toArray(request()));

        $responseData = [
            'counts' => $counts,
            'financial_stats' => $financialStats,
            'recent_enrollments' => $recentEnrollments,
            'upcoming_schedules' => $upcomingSchedules,
            'enrollments_by_year' => $enrollmentsByYear,
            'revenue_by_year' => $revenueByYear,
            'charts' => [
                'enrollments' => [
                    'categories' => $months,
                    'series' => [[ 'name' => 'Inscriptions (Effectif)', 'data' => $enrollmentSeries ]]
                ],
                'finances' => [
                    'categories' => $months,
                    'series' => [[ 'name' => 'Chiffre d\'Affaires (FCFA)', 'data' => $paymentSeries ]]
                ]
            ],
            'active_year' => (new SchoolYearResource($schoolYear))->toArray(request())
        ];
        
        // Log pour déboguer
        Log::info('Dashboard stats response', [
            'financial_stats_keys' => array_keys($financialStats),
            'financial_stats' => $financialStats,
            'enrollments_by_year_count' => count($enrollmentsByYear),
            'revenue_by_year_count' => count($revenueByYear),
            'response_data_keys' => array_keys($responseData),
        ]);
        
        return ApiResponse::sendResponse(true, $responseData, 'Statistiques du tableau de bord', 200);
    }
}
