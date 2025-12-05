<?php

namespace App\Interfaces;

use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GradeInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator;
	public function store(array $data): Grade;
	public function update(Grade $grade, array $data): Grade;
	public function delete(Grade $grade): void;
	public function getByStudent(int $studentId, array $filters = []): LengthAwarePaginator;
	public function getByTeacher(int $teacherId, array $filters = []): LengthAwarePaginator;
}
