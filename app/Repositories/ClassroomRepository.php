<?php

namespace App\Repositories;

use App\Interfaces\ClassroomInterface;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\StudentSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ClassroomRepository implements ClassroomInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Classroom::query();

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
					->orWhere('code', 'like', "%{$search}%")
					->orWhere('level', 'like', "%{$search}%");
			});
		}

		if (!empty($filters['cycle'])) {
			$query->where('cycle', $filters['cycle']);
		}

		if (!empty($filters['level'])) {
			$query->where('level', $filters['level']);
		}

		if (!empty($filters['school_year_id'])) {
			$query->where('school_year_id', $filters['school_year_id']);
		}

		return $query->orderBy('cycle')->orderBy('level')->orderBy('name')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Classroom
	{
		return Classroom::create($data);
	}

	public function update(Classroom $classroom, array $data): Classroom
	{
		$classroom->update($data);
		return $classroom;
	}

	public function delete(Classroom $classroom): void
	{
		$classroom->delete();
	}

	public function enrollStudents(Classroom $classroom, array $studentIds, string $schoolYear, ?string $enrolledAt = null): void
	{
		$date = $enrolledAt ? Carbon::parse($enrolledAt)->toDateString() : now()->toDateString();

		foreach ($studentIds as $studentId) {
			Enrollment::updateOrCreate(
				[
					'student_id' => $studentId,
					'school_year' => $schoolYear,
				],
				[
					'classroom_id' => $classroom->id,
					'enrolled_at' => $date,
				]
			);
		}

		$enrollments = $classroom->enrollments()
			->whereIn('student_id', $studentIds)
			->where('school_year', $schoolYear)
			->get(['student_id', 'school_year']);

		$this->syncStudentSubjectsForEnrollments($classroom, $enrollments);
	}

	public function syncSubjects(Classroom $classroom, array $subjectIds): void
	{
		$classroom->subjects()->sync($subjectIds);

		if (empty($subjectIds)) {
			StudentSubject::where('classroom_id', $classroom->id)->delete();
			return;
		}

		// Remove obsolete subject associations for students of this class
		StudentSubject::where('classroom_id', $classroom->id)
			->whereNotIn('subject_id', $subjectIds)
			->delete();

		$enrollments = $classroom->enrollments()->get(['student_id', 'school_year']);
		$this->syncStudentSubjectsForEnrollments($classroom, $enrollments);
	}

	public function assignTeachers(Classroom $classroom, int $subjectId, array $teacherIds): void
	{
		// ensure the subject is registered for the classroom
		$classroom->subjects()->syncWithoutDetaching([$subjectId]);

		$existing = DB::table('classroom_subject_teacher')
			->where('classroom_id', $classroom->id)
			->where('subject_id', $subjectId)
			->pluck('teacher_id')
			->toArray();

		$toRemove = array_diff($existing, $teacherIds);

		if (!empty($toRemove)) {
			DB::table('classroom_subject_teacher')
				->where('classroom_id', $classroom->id)
				->where('subject_id', $subjectId)
				->whereIn('teacher_id', $toRemove)
				->delete();
		}

		if (!empty($teacherIds)) {
			$now = now();
			$records = [];
			foreach ($teacherIds as $teacherId) {
				$records[] = [
					'classroom_id' => $classroom->id,
					'subject_id' => $subjectId,
					'teacher_id' => $teacherId,
					'created_at' => $now,
					'updated_at' => $now,
				];
			}

			DB::table('classroom_subject_teacher')->upsert(
				$records,
				['classroom_id', 'subject_id', 'teacher_id'],
				['updated_at']
			);
		}
	}

	private function syncStudentSubjectsForEnrollments(Classroom $classroom, Collection $enrollments): void
	{
		$subjectIds = $classroom->subjects()->pluck('subjects.id')->toArray();

		if (empty($subjectIds) || $enrollments->isEmpty()) {
			return;
		}

		$now = now();
		$records = [];

		foreach ($enrollments as $enrollment) {
			foreach ($subjectIds as $subjectId) {
				$records[] = [
					'student_id' => $enrollment->student_id,
					'subject_id' => $subjectId,
					'classroom_id' => $classroom->id,
					'school_year' => $enrollment->school_year,
					'created_at' => $now,
					'updated_at' => $now,
				];
			}
		}

		StudentSubject::upsert(
			$records,
			['student_id', 'subject_id', 'classroom_id', 'school_year'],
			['updated_at']
		);
	}
}

