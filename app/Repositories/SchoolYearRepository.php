<?php

namespace App\Repositories;

use App\Interfaces\SchoolYearInterface;
use App\Models\SchoolYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SchoolYearRepository implements SchoolYearInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = SchoolYear::query();

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('label', 'like', "%{$search}%")
					->orWhere('year_start', 'like', "%{$search}%");
			});
		}

		if (isset($filters['is_active'])) {
			$query->where('is_active', $filters['is_active']);
		}

		return $query->orderByDesc('year_start')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): SchoolYear
	{
		return SchoolYear::create($data);
	}

	public function update(SchoolYear $schoolYear, array $data): SchoolYear
	{
		$schoolYear->update($data);
		return $schoolYear->fresh();
	}

	public function delete(SchoolYear $schoolYear): void
	{
		$schoolYear->delete();
	}

	public function getActive(): ?SchoolYear
	{
		return SchoolYear::where('is_active', true)->first();
	}
}
