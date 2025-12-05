<?php

namespace App\Interfaces;

use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TeacherInterface
{
	/** @return LengthAwarePaginator */
	public function paginate(array $filters = []): LengthAwarePaginator;

	public function store(array $data): Teacher;

	public function update(Teacher $teacher, array $data): Teacher;

	public function delete(Teacher $teacher): void;
}
