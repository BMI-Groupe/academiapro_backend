<?php

namespace App\Http\Controllers;

use App\Models\ReportCard;
use App\Models\Student;
use App\Models\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportCardController extends Controller
{
    /**
     * List report cards for a student.
     */
    public function index(Request $request, $studentId)
    {
        $student = Student::findOrFail($studentId);
        
        $query = $student->reportCards()->with(['schoolYear', 'term']);

        if ($request->has('school_year_id')) {
            $query->where('school_year_id', $request->school_year_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    /**
     * Generate a report card for a student and a specific period/term.
     * This is a simplified version. In a real app, you'd calculate averages here.
     */
    public function generate(Request $request, $studentId)
    {
        $request->validate([
            'school_year_id' => 'required|exists:school_years,id',
            'term_id' => 'nullable|exists:terms,id', // If you have terms
            'title' => 'required|string',
        ]);

        $student = Student::findOrFail($studentId);
        
        // Logic to calculate grades would go here
        // For now, we'll just create a placeholder report card
        
        $reportCard = ReportCard::create([
            'student_id' => $student->id,
            'school_year_id' => $request->school_year_id,
            'title' => $request->title,
            'average' => 0, // Calculate this
            'rank' => 0, // Calculate this
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $reportCard,
            'message' => 'Report card generated successfully'
        ]);
    }

    /**
     * Download the report card as PDF.
     */
    public function download($id)
    {
        $reportCard = ReportCard::with(['student', 'schoolYear'])->findOrFail($id);
        
        // Load view for PDF
        // $pdf = Pdf::loadView('reports.card', compact('reportCard'));
        // return $pdf->download('report-card-'.$reportCard->id.'.pdf');

        return response()->json(['message' => 'PDF generation to be implemented']);
    }
}
