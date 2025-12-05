<?php

namespace App\Interfaces;

use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubjectInterface
{
	/** @return LengthAwarePaginator */
	public function paginate(array $filters = []): LengthAwarePaginator;

	public function store(array $data): Subject;

	public function update(Subject $subject, array $data): Subject;

	public function delete(Subject $subject): void;
}
