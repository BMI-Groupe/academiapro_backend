<?php

namespace App\Interfaces;

use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClassroomInterface
{
	/** @return LengthAwarePaginator */
	public function paginate(array $filters = []): LengthAwarePaginator;

	public function store(array $data): Section;

	public function update(Section $section, array $data): Section;

	public function delete(Section $section): void;

	public function enrollStudents(Section $section, array $studentIds, int $schoolYearId, ?string $enrolledAt = null): void;

	public function syncSubjects(Section $section, array $subjectIds): void;

	public function assignTeachers(Section $section, int $subjectId, array $teacherIds): void;
}


