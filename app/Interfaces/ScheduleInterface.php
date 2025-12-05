<?php

namespace App\Interfaces;

use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ScheduleInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator;
	public function store(array $data): Schedule;
	public function update(Schedule $schedule, array $data): Schedule;
	public function delete(Schedule $schedule): void;
	public function getByClassroom(int $classroomId, int $schoolYearId): LengthAwarePaginator;
	public function getByTeacher(int $teacherId, int $schoolYearId): LengthAwarePaginator;
}
