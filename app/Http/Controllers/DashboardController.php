<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;
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
            'classes' => Classroom::where('school_year_id', $schoolYearId)->count(),
            'teachers' => Teacher::count(), // Total global des enseignants
        ];

        // 2. Inscriptions récentes pour l'année
        $recentEnrollments = Enrollment::where('school_year_id', $schoolYearId)
            ->with(['student', 'classroom'])
            ->orderBy('enrolled_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($enr) {
                return [
                    'id' => $enr->student->id,
                    'first_name' => $enr->student->first_name,
                    'last_name' => $enr->student->last_name,
                    'matricule' => $enr->student->matricule,
                    'classroom' => $enr->classroom->name,
                    'label' => $enr->classroom->name, // Pour compatibilité
                    'created_at' => $enr->enrolled_at->toIso8601String()
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
                
            $paymentSeries[] = Payment::whereBetween('payment_date', [
                    $date->copy()->startOfMonth(),
                    $date->copy()->endOfMonth()
                ])
                ->sum('amount');
        }

        // Totaux financiers
        $totalRevenue = Payment::whereBetween('payment_date', [$start, $end])->sum('amount');
        $financialStats = [
            'total_revenue' => $totalRevenue,
            // On pourrait ajouter ici le prévisionnel si on avait les frais de scolarité par classe
        ];

        // 4. Emplois du temps (prochains cours du jour ou récents)
        // Note: Schedule n'a pas directement school_year_id, on passe par classroom -> school_year_id
        $upcomingSchedules = \App\Models\Schedule::whereHas('classroom', function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            })
            ->with(['classroom', 'subject', 'teacher.user'])
            ->orderBy('created_at', 'desc') // ou logique par jour de la semaine
            ->take(5)
            ->get();

        return ApiResponse::sendResponse(true, [
            'counts' => $counts,
            'financial_stats' => $financialStats,
            'recent_enrollments' => $recentEnrollments,
            'upcoming_schedules' => $upcomingSchedules,
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
            'active_year' => $schoolYear
        ], 'Statistiques du tableau de bord', 200);
    }
}
