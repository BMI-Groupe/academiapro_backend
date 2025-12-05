<?php

namespace App\Interfaces;

use App\Models\SchoolYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SchoolYearInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator;
	public function store(array $data): SchoolYear;
	public function update(SchoolYear $schoolYear, array $data): SchoolYear;
	public function delete(SchoolYear $schoolYear): void;
	public function getActive(): ?SchoolYear;
}
