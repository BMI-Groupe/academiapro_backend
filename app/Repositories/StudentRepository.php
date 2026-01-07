<?php

namespace App\Repositories;

use App\Interfaces\StudentInterface;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentRepository implements StudentInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Student::query()->with('enrollments.section.classroomTemplate');

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('first_name', 'like', "%{$search}%")
					->orWhere('last_name', 'like', "%{$search}%")
					->orWhere('matricule', 'like', "%{$search}%");
			});
		}

		if (!empty($filters['school_year_id'])) {
			// Inclure uniquement les élèves inscrits pour cette année scolaire
			$query->whereHas('enrollments', function ($eq) use ($filters) {
				$eq->where('school_year_id', $filters['school_year_id']);
			});
		}

		if (!empty($filters['classroom_id']) || !empty($filters['section_id'])) {
			$sectionId = $filters['section_id'] ?? $filters['classroom_id'];
			$query->whereHas('enrollments', function ($q) use ($sectionId) {
				$q->where('section_id', $sectionId);
			});
		}

		return $query->orderBy('last_name')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Student
	{
		$student = Student::create($data);

		// Inscription automatique si section_id (ou classroom_id pour compatibilité) est présent
		$sectionId = $data['section_id'] ?? $data['classroom_id'] ?? null;
		if ($sectionId) {
			$schoolYearId = $data['school_year_id'] ?? null;
			
			if (!$schoolYearId) {
				// Trouver l'année active pour cette école
				$activeYear = \App\Models\SchoolYear::where('school_id', $student->school_id)
					->where('is_active', true)
					->first();
				$schoolYearId = $activeYear?->id;
			}

			if ($schoolYearId) {
				\App\Models\Enrollment::create([
					'student_id' => $student->id,
					'section_id' => $sectionId,
					'school_year_id' => $schoolYearId,
					'enrolled_at' => now(),
					'status' => 'active',
				]);
			}
		}

		return $student->load('enrollments.section.classroomTemplate', 'enrollments.section.subjects');
	}

	public function update(Student $student, array $data): Student
	{
		$student->update($data);
		return $student->fresh()->load('enrollments.classroom.subjects');
	}

	public function delete(Student $student): void
	{
		$student->delete();
	}
}


