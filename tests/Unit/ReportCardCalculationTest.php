<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SchoolYear;
use App\Models\ClassroomSubject;
use App\Models\ReportCard;
use App\Jobs\CalculateReportCardJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportCardCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected $student;
    protected $classroom;
    protected $schoolYear;
    protected $mathSubject;
    protected $frenchSubject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->schoolYear = SchoolYear::factory()->create();
        $this->classroom = Classroom::factory()->create(['school_year_id' => $this->schoolYear->id]);
        $this->student = Student::factory()->create();

        $this->mathSubject = Subject::factory()->create(['name' => 'Mathématiques', 'code' => 'MATH']);
        $this->frenchSubject = Subject::factory()->create(['name' => 'Français', 'code' => 'FR']);

        // Set coefficients
        ClassroomSubject::create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->mathSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'coefficient' => 4,
        ]);

        ClassroomSubject::create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->frenchSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'coefficient' => 3,
        ]);
    }

    /** @test */
    public function it_calculates_quarterly_report_card_correctly()
    {
        // Create assignments for term 1
        $mathAssignment1 = Assignment::factory()->create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->mathSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'term' => 1,
        ]);

        $mathAssignment2 = Assignment::factory()->create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->mathSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'term' => 1,
        ]);

        $frenchAssignment = Assignment::factory()->create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->frenchSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'term' => 1,
        ]);

        // Create grades
        // Math: 15 and 17 -> average = 16
        Grade::factory()->create([
            'student_id' => $this->student->id,
            'assignment_id' => $mathAssignment1->id,
            'score' => 15,
        ]);

        Grade::factory()->create([
            'student_id' => $this->student->id,
            'assignment_id' => $mathAssignment2->id,
            'score' => 17,
        ]);

        // French: 14 -> average = 14
        Grade::factory()->create([
            'student_id' => $this->student->id,
            'assignment_id' => $frenchAssignment->id,
            'score' => 14,
        ]);

        // Calculate report card
        $job = new CalculateReportCardJob(
            $this->student->id,
            $this->schoolYear->id,
            $this->classroom->id,
            1 // Term 1
        );

        $job->handle();

        // Verify report card
        $reportCard = ReportCard::where('student_id', $this->student->id)
            ->where('school_year_id', $this->schoolYear->id)
            ->where('classroom_id', $this->classroom->id)
            ->where('term', 1)
            ->first();

        $this->assertNotNull($reportCard);

        // Expected: (16 * 4 + 14 * 3) / (4 + 3) = (64 + 42) / 7 = 106 / 7 = 15.14
        $this->assertEquals(15.14, $reportCard->average);
    }

    /** @test */
    public function it_calculates_annual_report_card_correctly()
    {
        // Create assignments across different terms
        $mathAssignment1 = Assignment::factory()->create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->mathSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'term' => 1,
        ]);

        $mathAssignment2 = Assignment::factory()->create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->mathSubject->id,
            'school_year_id' => $this->schoolYear->id,
            'term' => 2,
        ]);

        // Create grades
        Grade::factory()->create([
            'student_id' => $this->student->id,
            'assignment_id' => $mathAssignment1->id,
            'score' => 15,
        ]);

        Grade::factory()->create([
            'student_id' => $this->student->id,
            'assignment_id' => $mathAssignment2->id,
            'score' => 17,
        ]);

        // Calculate annual report card
        $job = new CalculateReportCardJob(
            $this->student->id,
            $this->schoolYear->id,
            $this->classroom->id,
            null // Annual
        );

        $job->handle();

        // Verify report card
        $reportCard = ReportCard::where('student_id', $this->student->id)
            ->where('school_year_id', $this->schoolYear->id)
            ->where('classroom_id', $this->classroom->id)
            ->whereNull('term')
            ->first();

        $this->assertNotNull($reportCard);
        // Math average: (15 + 17) / 2 = 16
        // Only math, so general average = 16
        $this->assertEquals(16.00, $reportCard->average);
    }

    /** @test */
    public function it_handles_empty_grades_gracefully()
    {
        // Calculate report card with no grades
        $job = new CalculateReportCardJob(
            $this->student->id,
            $this->schoolYear->id,
            $this->classroom->id,
            1
        );

        $job->handle();

        // Should not create a report card if no grades exist
        $reportCard = ReportCard::where('student_id', $this->student->id)
            ->where('term', 1)
            ->first();

        $this->assertNull($reportCard);
    }
}
