<?php

namespace App\Repositories;

use App\Interfaces\SubjectInterface;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubjectRepository implements SubjectInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Subject::query()->with(['classrooms']);

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
					->orWhere('code', 'like', "%{$search}%");
			});
		}

		if (!empty($filters['school_year_id'])) {
			$query->whereHas('classroomSubjects', function ($q) use ($filters) {
				$q->where('school_year_id', $filters['school_year_id']);
			});
		}

		return $query->orderBy('name')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Subject
	{
		return Subject::create($data)->load(['classrooms']);
	}

	public function update(Subject $subject, array $data): Subject
	{
		$subject->update($data);
		return $subject->fresh()->load(['classrooms']);
	}

	public function delete(Subject $subject): void
	{
		$subject->delete();
	}
}
