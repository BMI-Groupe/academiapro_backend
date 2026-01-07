<?php

namespace App\Http\Controllers;

use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Grade;
use App\Models\SectionSubject;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Jobs\CalculateReportCardJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Responses\ApiResponse;

class ReportCardController extends Controller
{
    /**
     * List report cards for a student.
     * Automatically calculates report cards if they don't exist but grades are available.
     */
    public function index(Request $request, $studentId)
    {
        $student = Student::findOrFail($studentId);
        
        $schoolYearId = $request->has('school_year_id') 
            ? $request->school_year_id 
            : SchoolYear::active()?->id;

        if (!$schoolYearId) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Get enrollment for this school year to get section_id
        $enrollment = Enrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $sectionId = $enrollment->section_id;
        $schoolYear = SchoolYear::find($schoolYearId);

        // Récupérer tous les assignments de la section pour cette année
        $assignmentIds = \App\Models\Assignment::where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->pluck('id');

        // Check if student has grades for this school year
        $hasGrades = false;
        $periodsWithGrades = [];
        
        if ($assignmentIds->isNotEmpty()) {
            $hasGrades = Grade::where('student_id', $studentId)
                ->whereIn('assignment_id', $assignmentIds)
                ->exists();

            Log::info("Checking report cards for student", [
                'student_id' => $studentId,
                'school_year_id' => $schoolYearId,
                'section_id' => $sectionId,
                'has_grades' => $hasGrades,
                'assignment_ids_count' => $assignmentIds->count()
            ]);

            // If student has grades, check if report cards exist
            if ($hasGrades) {
                // Find which periods actually have grades
                $grades = Grade::where('student_id', $studentId)
                    ->whereIn('assignment_id', $assignmentIds)
                    ->with('assignment')
                    ->get();
                
                $periodsWithGrades = $grades->pluck('assignment.period')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                Log::info("Periods with grades", [
                    'student_id' => $studentId,
                    'periods' => $periodsWithGrades
                ]);
            }
        }
        
        // If student has grades, create/update report cards for each unique assignment
        if ($hasGrades) {
            // Récupérer tous les devoirs uniques qui ont des notes pour cet élève
            $uniqueAssignmentIds = Grade::where('student_id', $studentId)
                ->whereIn('assignment_id', $assignmentIds)
                ->distinct()
                ->pluck('assignment_id')
                ->toArray();

            $existingReportCards = ReportCard::where('student_id', $studentId)
                ->where('school_year_id', $schoolYearId)
                ->where('section_id', $sectionId)
                ->whereNotNull('assignment_id')
                ->pluck('assignment_id')
                ->toArray();

            // Créer ou mettre à jour un bulletin pour chaque devoir unique
            foreach ($uniqueAssignmentIds as $assignmentId) {
                try {
                    $assignment = \App\Models\Assignment::find($assignmentId);
                    if (!$assignment) {
                        continue;
                    }

                    // Récupérer toutes les notes de cet élève pour ce devoir
                    $allGrades = \App\Models\Grade::where('student_id', $studentId)
                        ->where('assignment_id', $assignmentId)
                        ->get();

                    if ($allGrades->isEmpty()) {
                        continue;
                    }

                    // Calculer la moyenne de toutes les notes pour ce devoir
                    $average = round($allGrades->avg('score'), 2);

                    // Créer ou mettre à jour le bulletin
                    $reportCard = ReportCard::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'school_year_id' => $schoolYearId,
                            'section_id' => $sectionId,
                            'assignment_id' => $assignmentId,
                        ],
                        [
                            'average' => $average,
                            'period' => $assignment->period,
                            'generated_at' => now(),
                        ]
                    );
                    
                    Log::info("Report card created/updated for assignment", [
                        'student_id' => $studentId,
                        'school_year_id' => $schoolYearId,
                        'section_id' => $sectionId,
                        'assignment_id' => $assignmentId,
                        'report_card_id' => $reportCard->id,
                        'average' => $average,
                        'grades_count' => $allGrades->count()
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to create/update report card for assignment", [
                        'student_id' => $studentId,
                        'school_year_id' => $schoolYearId,
                        'section_id' => $sectionId,
                        'assignment_id' => $assignmentId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }
        
        // Return all report cards for this student and school year
        $query = $student->reportCards()->with(['schoolYear', 'section.classroomTemplate', 'assignment.subject'])
            ->where('school_year_id', $schoolYearId);

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    /**
     * Show a detailed report card.
     */
    public function show($id)
    {
        $reportCard = ReportCard::with(['student', 'schoolYear', 'section.classroomTemplate.school', 'assignment.subject'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->buildReportCardData($reportCard)
        ]);
    }

    /**
     * Build the structured data for the report card view.
     */
    private function buildReportCardData(ReportCard $reportCard)
    {
        // Si c'est un bulletin pour un devoir spécifique
        if ($reportCard->assignment_id) {
            return $this->buildAssignmentReportCard($reportCard);
        }
        
        // Sinon, c'est un bulletin consolidé (période ou annuel)
        return $this->buildPeriodReportCard($reportCard);
    }

    /**
     * Build report card data for a specific assignment
     * Affiche toutes les notes de l'élève pour ce devoir (toutes les matières)
     */
    private function buildAssignmentReportCard(ReportCard $reportCard)
    {
        $assignment = \App\Models\Assignment::find($reportCard->assignment_id);
        
        if (!$assignment) {
            throw new \Exception('Devoir non trouvé pour ce bulletin');
        }

        // Récupérer TOUTES les notes de l'élève pour ce devoir
        $grades = Grade::where('student_id', $reportCard->student_id)
            ->where('assignment_id', $reportCard->assignment_id)
            ->with(['assignment.subject'])
            ->get();

        if ($grades->isEmpty()) {
            throw new \Exception('Aucune note trouvée pour ce devoir');
        }

        // Si le devoir a un subject_id spécifique, toutes les notes sont pour cette matière
        // Sinon, le devoir s'applique à toutes les matières et on doit grouper par matière
        if ($assignment->subject_id) {
            // Devoir pour une matière spécifique
            $subject = $assignment->subject;
            if (!$subject) {
                throw new \Exception('Matière non trouvée pour ce devoir');
            }

            // Récupérer toutes les notes de l'élève pour ce devoir (normalement une seule)
            $grade = $grades->first();
            if (!$grade) {
                throw new \Exception('Aucune note trouvée pour ce devoir');
            }

            // Récupérer le coefficient
            $sectionSubject = \App\Models\SectionSubject::where('section_id', $reportCard->section_id)
                ->where('subject_id', $assignment->subject_id)
                ->where('school_year_id', $reportCard->school_year_id)
                ->first();
            
            $coefficient = $sectionSubject ? $sectionSubject->coefficient : 1;

            // Statistiques de classe pour ce devoir
            $classStats = $this->getClassStatsForAssignment($reportCard->assignment_id, $reportCard->section_id);

            $subjectsData = [[
                'name' => $subject->name,
                'teacher' => 'Enseignant',
                'coefficient' => $coefficient,
                'studentAverage' => $grade->score,
                'classMin' => $classStats['min'],
                'classMax' => $classStats['max'],
                'classAverage' => $classStats['avg'],
                'appreciation' => $this->getAppreciationForScore($grade->score, $assignment->max_score ?? 20),
            ]];

            $generalAverage = $grade->score;
        } else {
            // Devoir pour toutes les matières - grouper les notes par matière
            // Pour cela, on doit utiliser les matières de la section et trouver quelle note correspond à quelle matière
            // Comme on ne peut pas déterminer directement, on va supposer que chaque note correspond à une matière différente
            // et on va les afficher toutes
            
            // Récupérer toutes les matières de la section
            $sectionSubjects = \App\Models\SectionSubject::where('section_id', $reportCard->section_id)
                ->where('school_year_id', $reportCard->school_year_id)
                ->with('subject')
                ->get();

            $subjectsData = [];
            $totalWeightedScore = 0;
            $totalCoefficient = 0;

            // Pour chaque note, on va essayer de trouver la matière correspondante
            // Si on a le même nombre de notes que de matières, on peut les associer
            // Sinon, on affiche toutes les notes avec leur score
            foreach ($grades as $index => $grade) {
                // Si on a des matières disponibles, utiliser celle correspondant à l'index
                // Sinon, créer une entrée générique
                $sectionSubject = $sectionSubjects->get($index);
                
                if ($sectionSubject && $sectionSubject->subject) {
                    $subject = $sectionSubject->subject;
                    $coefficient = $sectionSubject->coefficient;
                } else {
                    // Matière non trouvée, utiliser une valeur par défaut
                    $subject = (object)['id' => null, 'name' => 'Matière ' . ($index + 1)];
                    $coefficient = 1;
                }

                // Statistiques de classe pour cette note (moyenne de toutes les notes du devoir)
                $classStats = $this->getClassStatsForAssignment($reportCard->assignment_id, $reportCard->section_id);

                $subjectsData[] = [
                    'name' => $subject->name,
                    'teacher' => 'Enseignant',
                    'coefficient' => $coefficient,
                    'studentAverage' => $grade->score,
                    'classMin' => $classStats['min'],
                    'classMax' => $classStats['max'],
                    'classAverage' => $classStats['avg'],
                    'appreciation' => $this->getAppreciationForScore($grade->score, $assignment->max_score ?? 20),
                ];

                // Calculer la moyenne pondérée
                $totalWeightedScore += $grade->score * $coefficient;
                $totalCoefficient += $coefficient;
            }

            // Calculer la moyenne générale pondérée
            $generalAverage = $totalCoefficient > 0 ? round($totalWeightedScore / $totalCoefficient, 2) : round($grades->avg('score'), 2);
        }

        // Déterminer la date (utiliser la date la plus récente des notes ou celle du devoir)
        $date = null;
        $latestGrade = $grades->sortByDesc('graded_at')->first();
        if ($latestGrade && $latestGrade->graded_at) {
            $date = is_string($latestGrade->graded_at) ? $latestGrade->graded_at : $latestGrade->graded_at->format('Y-m-d');
        } elseif ($assignment->due_date) {
            $date = is_string($assignment->due_date) ? $assignment->due_date : $assignment->due_date->format('Y-m-d');
        }

        // Check if section exists
        $section = $reportCard->section;
        $classroomName = $section ? ($section->display_name ?? $section->name ?? $section->classroomTemplate->name ?? 'Classe inconnue') : 'Classe inconnue';
        $school = $section && $section->classroomTemplate ? $section->classroomTemplate->school : null;
        $schoolName = $school ? $school->name : 'École';
        $schoolAddress = $school ? $school->address : '';
        $schoolPhone = $school ? $school->phone : '';

        return [
            'id' => $reportCard->id,
            'student' => [
                'firstName' => $reportCard->student->first_name,
                'lastName' => $reportCard->student->last_name,
                'matricule' => $reportCard->student->matricule,
            ],
            'school' => [
                'name' => $schoolName,
                'address' => $schoolAddress,
                'phone' => $schoolPhone,
            ],
            'schoolYear' => $reportCard->schoolYear->label,
            'period' => $assignment->title . ' - ' . ($assignment->type ?? 'Devoir'),
            'classroom' => $classroomName,
            'subjects' => $subjectsData,
            'generalAverage' => $generalAverage,
            'rank' => $reportCard->rank ?? 0,
            'totalStudents' => 0,
            'absences' => $reportCard->absences ?? 0,
            'mention' => $this->getMention($generalAverage),
            'councilAppreciation' => $reportCard->comments ?? '',
            'assignment' => [
                'title' => $assignment->title,
                'type' => $assignment->type ?? 'Devoir',
                'date' => $date,
                'max_score' => $assignment->max_score ?? 20,
            ],
        ];
    }

    /**
     * Build report card data for a period (consolidated)
     */
    private function buildPeriodReportCard(ReportCard $reportCard)
    {
        // 1. Récupérer tous les assignments de la section pour cette année
        $assignmentQuery = \App\Models\Assignment::where('section_id', $reportCard->section_id)
            ->where('school_year_id', $reportCard->school_year_id);
            
        if ($reportCard->period) {
            $assignmentQuery->where('period', $reportCard->period);
        }
        
        $assignmentIds = $assignmentQuery->pluck('id');
        
        // 2. Get all grades for this report card's period context
        $grades = Grade::where('student_id', $reportCard->student_id)
            ->whereIn('assignment_id', $assignmentIds)
            ->with(['assignment.subject']) // Load assignment and subject
            ->get()
            ->filter(function($grade) {
                // Filtrer les notes qui ont un assignment et un subject valides
                return $grade->assignment && $grade->assignment->subject_id && $grade->assignment->subject;
            });

        // 2. Group by subject
        $subjectsData = [];
        $gradesBySubject = $grades->groupBy(function($grade) {
            return $grade->assignment->subject_id ?? 'unknown';
        });

        foreach ($gradesBySubject as $subjectId => $subjectGrades) {
            if ($subjectGrades->isEmpty()) continue;

            $firstGrade = $subjectGrades->first();
            $subject = $firstGrade->assignment->subject ?? null;
            
            // Skip if subject is null or subjectId is invalid
            if (!$subject || !$subjectId || $subjectId === 'unknown') {
                continue;
            }
            
            // Get coefficient
            $sectionSubject = \App\Models\SectionSubject::where('section_id', $reportCard->section_id)
                ->where('subject_id', $subjectId)
                ->where('school_year_id', $reportCard->school_year_id)
                ->first();
            
            $coefficient = $sectionSubject ? $sectionSubject->coefficient : 1;
            $studentAverage = $this->calculateSubjectAverage($subjectGrades);

            $classStats = $this->getClassStats($reportCard, $subjectId);

            $subjectsData[] = [
                'name' => $subject->name ?? 'Matière inconnue',
                'teacher' => 'Enseignant', 
                'coefficient' => $coefficient,
                'studentAverage' => $studentAverage,
                'classMin' => $classStats['min'],
                'classMax' => $classStats['max'],
                'classAverage' => $classStats['avg'],
                'appreciation' => 'Trimestre satisfaisant',
            ];
        }

        // Check if section exists
        $section = $reportCard->section;
        $classroomName = $section ? ($section->display_name ?? $section->name ?? $section->classroomTemplate->name ?? 'Classe inconnue') : 'Classe inconnue';
        $school = $section && $section->classroomTemplate ? $section->classroomTemplate->school : null;
        $schoolName = $school ? $school->name : 'École';
        $schoolAddress = $school ? $school->address : '';
        $schoolPhone = $school ? $school->phone : '';

        return [
            'id' => $reportCard->id,
            'student' => [
                'firstName' => $reportCard->student->first_name,
                'lastName' => $reportCard->student->last_name,
                'matricule' => $reportCard->student->matricule,
            ],
            'school' => [
                'name' => $schoolName,
                'address' => $schoolAddress,
                'phone' => $schoolPhone,
            ],
            'schoolYear' => $reportCard->schoolYear->label,
            'period' => $reportCard->period ? ($reportCard->schoolYear->period_system === 'semester' ? 'Semestre ' . $reportCard->period : 'Trimestre ' . $reportCard->period) : 'Annuel',
            'classroom' => $classroomName,
            'subjects' => $subjectsData,
            'generalAverage' => $reportCard->average,
            'rank' => $reportCard->rank,
            'totalStudents' => 0, 
            'absences' => $reportCard->absences ?? 0,
            'mention' => $this->getMention($reportCard->average),
            'councilAppreciation' => $reportCard->comments ?? ''
        ];
    }

    /**
     * Get class statistics for a specific assignment
     */
    private function getClassStatsForAssignment($assignmentId, $sectionId)
    {
        $grades = Grade::where('assignment_id', $assignmentId)
            ->whereHas('student.enrollments', function($q) use ($sectionId) {
                $q->where('section_id', $sectionId);
            })
            ->get();

        if ($grades->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'avg' => 0];
        }

        return [
            'min' => round($grades->min('score'), 2),
            'max' => round($grades->max('score'), 2),
            'avg' => round($grades->avg('score'), 2),
        ];
    }

    /**
     * Get appreciation for a score
     */
    private function getAppreciationForScore($score, $maxScore = 20)
    {
        $percentage = ($score / $maxScore) * 100;
        
        if ($percentage >= 90) return 'Excellent';
        if ($percentage >= 80) return 'Très bien';
        if ($percentage >= 70) return 'Bien';
        if ($percentage >= 60) return 'Assez bien';
        if ($percentage >= 50) return 'Passable';
        return 'Insuffisant';
    }

    public function update(Request $request, ReportCard $reportCard)
    {
        $validated = $request->validate([
            'absences' => 'nullable|integer|min:0',
            'comments' => 'nullable|string'
        ]);

        $reportCard->update($validated);

        return ApiResponse::sendResponse(true, [$reportCard], 'Bulletin mis à jour.', 200);
    }

    private function calculateSubjectAverage($grades) {
        if ($grades->isEmpty()) return 0;
        return round($grades->avg('score'), 2);
    }

    private function getClassStats($reportCard, $subjectId) {
        // Récupérer tous les assignments de la section pour cette année et cette matière
        $assignmentQuery = \App\Models\Assignment::where('section_id', $reportCard->section_id)
            ->where('school_year_id', $reportCard->school_year_id)
            ->where('subject_id', $subjectId);
            
        if ($reportCard->period) {
            $assignmentQuery->where('period', $reportCard->period);
        }
        
        $assignmentIds = $assignmentQuery->pluck('id');
        
        // Query grades using assignment IDs
        $query = Grade::whereIn('assignment_id', $assignmentIds);

        // We need averages PER STUDENT first
        $allGrades = $query->get();
        // ... rest logic same

        // We need averages PER STUDENT first
        $allGrades = $query->get();
        $studentAverages = $allGrades->groupBy('student_id')->map(function($g) {
            return $g->avg('score');
        });

        if ($studentAverages->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'avg' => 0];
        }

        return [
            'min' => round($studentAverages->min(), 2),
            'max' => round($studentAverages->max(), 2),
            'avg' => round($studentAverages->avg(), 2),
        ];
    }

    private function getMention($average) {
        if ($average >= 18) return 'Félicitations';
        if ($average >= 16) return 'Très Bien';
        if ($average >= 14) return 'Bien';
        if ($average >= 12) return 'Assez Bien';
        if ($average >= 10) return 'Passable';
        return 'Insuffisant';
    }

    public function generate(Request $request, $studentId)
    {
       // Implementation omitted for brevity, logic moved to Jobs
        return response()->json(['message' => 'Use artisan reports:recalculate for now']);
    }

    public function download($id)
    {
        return response()->json(['message' => 'PDF Not implemented yet']);
    }
}
