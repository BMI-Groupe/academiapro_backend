<?php

namespace App\Repositories;

use App\Interfaces\StudentInterface;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentRepository implements StudentInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Student::query()->with('enrollments.classroom.subjects');

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('first_name', 'like', "%{$search}%")
					->orWhere('last_name', 'like', "%{$search}%")
					->orWhere('matricule', 'like', "%{$search}%");
			});
		}

		if (!empty($filters['school_year_id'])) {
			$query->whereHas('enrollments', function ($q) use ($filters) {
				$q->where('school_year_id', $filters['school_year_id']);
			});
		}

		return $query->orderBy('last_name')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Student
	{
		return Student::create($data)->load('enrollments.classroom.subjects');
	}

	public function update(Student $student, array $data): Student
	{
		$student->update($data);
		return $student->fresh()->load('enrollments.classroom.subjects');
	}

	public function delete(Student $student): void
	{
		$student->delete();
	}
}


