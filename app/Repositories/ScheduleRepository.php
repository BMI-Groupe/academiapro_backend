<?php

namespace App\Repositories;

use App\Interfaces\ScheduleInterface;
use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ScheduleRepository implements ScheduleInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Schedule::query()
			->with(['section.classroomTemplate', 'subject', 'teacher', 'schoolYear']);

		if (!empty($filters['classroom_id']) || !empty($filters['section_id'])) {
			$sectionId = $filters['section_id'] ?? $filters['classroom_id'];
			$query->where('section_id', $sectionId);
		}

		if (!empty($filters['teacher_id'])) {
			$query->where('teacher_id', $filters['teacher_id']);
		}

		if (!empty($filters['school_year_id'])) {
			$query->where('school_year_id', $filters['school_year_id']);
		}

		if (!empty($filters['day_of_week'])) {
			$query->where('day_of_week', $filters['day_of_week']);
		}

		return $query->orderBy('day_of_week')
			->orderBy('start_time')
			->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Schedule
	{
		return Schedule::create($data)->load(['section.classroomTemplate', 'subject', 'teacher', 'schoolYear']);
	}

	public function update(Schedule $schedule, array $data): Schedule
	{
		$schedule->update($data);
		return $schedule->fresh()->load(['section.classroomTemplate', 'subject', 'teacher', 'schoolYear']);
	}

	public function delete(Schedule $schedule): void
	{
		$schedule->delete();
	}

	public function getByClassroom(int $sectionId, int $schoolYearId): LengthAwarePaginator
	{
		return Schedule::query()
			->where('section_id', $sectionId)
			->where('school_year_id', $schoolYearId)
			->with(['subject', 'teacher', 'section.classroomTemplate'])
			->orderBy('day_of_week')
			->orderBy('start_time')
			->paginate(50);
	}

	public function getByTeacher(int $teacherId, int $schoolYearId): LengthAwarePaginator
	{
		return Schedule::query()
			->where('teacher_id', $teacherId)
			->where('school_year_id', $schoolYearId)
			->with(['section.classroomTemplate', 'subject'])
			->orderBy('day_of_week')
			->orderBy('start_time')
			->paginate(50);
	}
}
