<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService)
    {
    }

    /**
     * List all payments
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->query('search'),
            'school_year_id' => $request->query('school_year_id'),
            'per_page' => $request->query('per_page'),
        ];

        $payments = $this->paymentService->getAllPayments($filters);

        // Transformation pour inclure le solde restant si nécessaire (optimisation à voir)
        // Pour l'instant, on renvoie les paiements tels quels avec les relations chargées via le service.
        
        return ApiResponse::sendResponse(true, $payments, 'Liste des paiements récupérée.', 200);
    }

    /**
     * Get financial balance for a student
     */
    public function getStudentBalance(Request $request, Student $student)
    {
        $schoolYearId = $request->query('school_year_id');
        $balance = $this->paymentService->getStudentBalance($student->id, $schoolYearId);

        return ApiResponse::sendResponse(true, $balance, 'Bilan financier récupéré.', 200);
    }

    /**
     * Get payments history for a student
     */
    public function getStudentPayments(Request $request, Student $student)
    {
        $schoolYearId = $request->query('school_year_id');
        $payments = $this->paymentService->getStudentPayments($student->id, $schoolYearId);

        return ApiResponse::sendResponse(true, $payments, 'Historique des paiements récupéré.', 200);
    }

    /**
     * Record a new payment
     */
    /**
     * Record a new payment
     */
    public function store(\App\Http\Requests\PaymentStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        try {
            $payment = $this->paymentService->recordPayment($validated);
            return ApiResponse::sendResponse(true, [$payment], 'Paiement enregistré avec succès.', 201);
        } catch (\Exception $e) {
            return ApiResponse::rollback($e);
        }
    }

    /**
     * Get payment details
     */
    public function show(Payment $payment)
    {
        $payment->load(['student', 'user', 'schoolYear']);
        return ApiResponse::sendResponse(true, $payment, 'Détails du paiement récupérés.', 200);
    }

    /**
     * Generate receipt PDF (Placeholder for now)
     */
    public function downloadReceipt(Payment $payment)
    {
        // TODO: Implement PDF generation
        return ApiResponse::sendResponse(true, [], 'Fonctionnalité à venir.', 200);
    }
}
