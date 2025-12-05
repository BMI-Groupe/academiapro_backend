<?php

namespace App\Repositories;

use App\Interfaces\TeacherInterface;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeacherRepository implements TeacherInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Teacher::query()->with(['user', 'classroomSubjectTeachers.classroomSubject.classroom', 'classroomSubjectTeachers.classroomSubject.subject']);

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('first_name', 'like', "%{$search}%")
					->orWhere('last_name', 'like', "%{$search}%")
					->orWhere('specialization', 'like', "%{$search}%")
					->orWhere('phone', 'like', "%{$search}%");
			});
		}

		if (!empty($filters['school_year_id'])) {
			$query->whereHas('classroomSubjectTeachers.classroomSubject', function ($q) use ($filters) {
				$q->where('school_year_id', $filters['school_year_id']);
			});
		}

		return $query->orderBy('last_name')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Teacher
	{
		// Ensure we have a linked user_id (migration requires a non-null unique user_id)
		if (empty($data['user_id'])) {
			$name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: 'Utilisateur';
			// generate a unique local email
			$email = Str::slug(($data['first_name'] ?? 'user') . '.' . ($data['last_name'] ?? '')) . '.' . time() . '@local.test';
			$user = User::create([
				'name' => $name,
				'email' => $email,
				'password' => Hash::make(Str::random(12)),
				'role' => 'enseignant',
				'phone' => $data['phone'] ?? null,
			]);

			$data['user_id'] = $user->id;
		}

		return Teacher::create($data)->load(['user', 'classroomSubjectTeachers.classroomSubject.classroom', 'classroomSubjectTeachers.classroomSubject.subject']);
	}

	public function update(Teacher $teacher, array $data): Teacher
	{
		$teacher->update($data);
		return $teacher->fresh()->load(['user', 'classroomSubjectTeachers.classroomSubject.classroom', 'classroomSubjectTeachers.classroomSubject.subject']);
	}

	public function delete(Teacher $teacher): void
	{
		$teacher->delete();
	}
}
