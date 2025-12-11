<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Classroom;
use Illuminate\Support\Str;
use App\Models\Enrollment;
use Exception;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Obtenir le bilan financier d'un élève pour une année donnée
     */
    public function getStudentBalance($studentId, $schoolYearId = null)
    {
        if (!$schoolYearId) {
            $schoolYearId = SchoolYear::active()->id;
        }

        // Trouver l'inscription de l'élève pour l'année donnée pour connaître sa classe
        $enrollment = Enrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->with('classroom')
            ->first();

        if (!$enrollment) {
            return [
                'total_due' => 0,
                'total_paid' => 0,
                'balance' => 0,
                'currency' => 'XOF'
            ];
        }

        $tuitionFee = $enrollment->classroom->tuition_fee ?? 0;
        
        $totalPaid = Payment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->where('type', 'TUITION')
            ->sum('amount');

        return [
            'total_due' => $tuitionFee,
            'total_paid' => $totalPaid,
            'balance' => $tuitionFee - $totalPaid,
            'currency' => 'XOF',
            'classroom' => $enrollment->classroom->name
        ];
    }

    /**
     * Enregistrer un nouveau paiement
     */
    public function recordPayment(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Validation basique (s'assurer que l'année est active si non spécifiée)
            if (empty($data['school_year_id'])) {
                $data['school_year_id'] = SchoolYear::active()->id;
            }

            if (empty($data['school_id'])) {
                $user = auth()->user();
                if ($user && $user->school_id) {
                    $data['school_id'] = $user->school_id;
                } else {
                    $firstSchool = \App\Models\School::first();
                    if ($firstSchool) {
                         $data['school_id'] = $firstSchool->id;
                    }
                }
            }

            // Générer une référence unique si pas fournie
            if (empty($data['reference'])) {
                $data['reference'] = 'PAY-' . strtoupper(Str::random(10));
            }

            // Créer le paiement
            $payment = Payment::create($data);

            return $payment;
        });
    }

    /**
     * Lister les paiements d'un élève
     */
    public function getStudentPayments($studentId, $schoolYearId = null)
    {
        if (!$schoolYearId) {
            $schoolYearId = SchoolYear::active()->id;
        }

        return Payment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Obtenir la liste globale des paiements (avec filtres et pagination)
     */
    public function getAllPayments(array $filters = [])
    {
        $query = Payment::with(['student.enrollments.classroom', 'schoolYear', 'user'])
            ->orderBy('payment_date', 'desc');

        // Filtrer par année scolaire
        if (!empty($filters['school_year_id'])) {
            $query->where('school_year_id', $filters['school_year_id']);
        } else {
            // Par défaut, année active
            $activeYear = SchoolYear::active();
            if ($activeYear) {
                $query->where('school_year_id', $activeYear->id);
            }
        }

        // Filtrer par recherche (nom élève, référence)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
