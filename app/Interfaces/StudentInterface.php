<?php

namespace App\Interfaces;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentInterface
{
	/** @return LengthAwarePaginator */
	public function paginate(array $filters = []): LengthAwarePaginator;

	public function store(array $data): Student;

	public function update(Student $student, array $data): Student;

	public function delete(Student $student): void;
}


