<?php

namespace App\Repositories;

use App\Interfaces\GradeInterface;
use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GradeRepository implements GradeInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Grade::query()
			->with(['student', 'assignment.subject', 'assignment.section.classroomTemplate', 'assignment.schoolYear', 'grader']);

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->whereHas('student', function (Builder $sq) use ($search) {
					$sq->where('first_name', 'like', "%{$search}%")
						->orWhere('last_name', 'like', "%{$search}%")
						->orWhere('matricule', 'like', "%{$search}%");
				})->orWhereHas('assignment', function (Builder $aq) use ($search) {
					$aq->where('title', 'like', "%{$search}%");
				});
			});
		}

		if (!empty($filters['student_id'])) {
			$query->where('student_id', $filters['student_id']);
		}

		if (!empty($filters['subject_id'])) {
			$query->whereHas('assignment', function (Builder $q) use ($filters) {
				$q->where('subject_id', $filters['subject_id']);
			});
		}

		if (!empty($filters['classroom_id']) || !empty($filters['section_id'])) {
			$sectionId = $filters['section_id'] ?? $filters['classroom_id'];
			$query->whereHas('assignment', function (Builder $q) use ($sectionId) {
				$q->where('section_id', $sectionId);
			});
		}

		if (!empty($filters['school_year_id'])) {
			$query->whereHas('assignment', function (Builder $q) use ($filters) {
				$q->where('school_year_id', $filters['school_year_id']);
			});
		}

		if (!empty($filters['assignment_type'])) {
			$query->whereHas('assignment', function (Builder $q) use ($filters) {
				$q->where('type', $filters['assignment_type']);
			});
		}

		return $query->orderByDesc('graded_at')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Grade
	{
		return Grade::create($data)->load(['student', 'assignment.subject', 'assignment.section.classroomTemplate', 'assignment.schoolYear', 'grader']);
	}

	public function update(Grade $grade, array $data): Grade
	{
		$grade->update($data);
		return $grade->fresh()->load(['student', 'assignment.subject', 'assignment.section.classroomTemplate', 'assignment.schoolYear', 'grader']);
	}

	public function delete(Grade $grade): void
	{
		$grade->delete();
	}

	public function getByStudent(int $studentId, array $filters = []): LengthAwarePaginator
	{
		$query = Grade::query()
			->where('student_id', $studentId)
			->with(['assignment.subject', 'assignment.section.classroomTemplate', 'assignment.schoolYear', 'grader']);

		if (!empty($filters['school_year_id'])) {
			$query->whereHas('assignment', function (Builder $q) use ($filters) {
				$q->where('school_year_id', $filters['school_year_id']);
			});
		}

		if (!empty($filters['subject_id'])) {
			$query->whereHas('assignment', function (Builder $q) use ($filters) {
				$q->where('subject_id', $filters['subject_id']);
			});
		}

		return $query->orderByDesc('graded_at')->paginate($filters['per_page'] ?? 15);
	}

	public function getByTeacher(int $teacherId, array $filters = []): LengthAwarePaginator
	{
		$query = Grade::query()
			->where('graded_by', $teacherId)
			->with(['student', 'assignment.subject', 'assignment.section.classroomTemplate', 'assignment.schoolYear']);

		if (!empty($filters['school_year_id'])) {
			$query->whereHas('assignment', function (Builder $q) use ($filters) {
				$q->where('school_year_id', $filters['school_year_id']);
			});
		}

		if (!empty($filters['classroom_id']) || !empty($filters['section_id'])) {
			$sectionId = $filters['section_id'] ?? $filters['classroom_id'];
			$query->whereHas('assignment', function (Builder $q) use ($sectionId) {
				$q->where('section_id', $sectionId);
			});
		}

		return $query->orderByDesc('graded_at')->paginate($filters['per_page'] ?? 15);
	}
}
