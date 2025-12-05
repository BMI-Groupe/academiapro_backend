<?php

namespace App\Interfaces;

use App\Models\Classroom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClassroomInterface
{
	/** @return LengthAwarePaginator */
	public function paginate(array $filters = []): LengthAwarePaginator;

	public function store(array $data): Classroom;

	public function update(Classroom $classroom, array $data): Classroom;

	public function delete(Classroom $classroom): void;

	public function enrollStudents(Classroom $classroom, array $studentIds, string $schoolYear, ?string $enrolledAt = null): void;

	public function syncSubjects(Classroom $classroom, array $subjectIds): void;

	public function assignTeachers(Classroom $classroom, int $subjectId, array $teacherIds): void;
}


