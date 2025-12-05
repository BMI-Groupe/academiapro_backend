<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleStoreRequest;
use App\Http\Requests\ScheduleUpdateRequest;
use App\Http\Resources\ScheduleResource;
use App\Interfaces\ScheduleInterface;
use App\Models\Schedule;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
	private ScheduleInterface $schedules;

	public function __construct(ScheduleInterface $schedules)
	{
		$this->schedules = $schedules;
	}

	public function index(Request $request)
	{
		$filters = [
			'classroom_id' => $request->query('classroom_id'),
			'teacher_id' => $request->query('teacher_id'),
			'school_year_id' => $request->query('school_year_id'),
			'day_of_week' => $request->query('day_of_week'),
			'per_page' => $request->query('per_page'),
		];

		$data = $this->schedules->paginate($filters)->through(fn ($s) => new ScheduleResource($s));
		return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
	}

	public function store(ScheduleStoreRequest $request)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$schedule = $this->schedules->store($request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new ScheduleResource($schedule)], 'Emploi du temps créé.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Schedule $schedule)
	{
		return ApiResponse::sendResponse(true, [new ScheduleResource($schedule->load(['classroom', 'subject', 'teacher', 'schoolYear']))], 'Opération effectuée.', 200);
	}

	public function update(ScheduleUpdateRequest $request, Schedule $schedule)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$schedule = $this->schedules->update($schedule, $request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new ScheduleResource($schedule)], 'Emploi du temps mis à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Schedule $schedule)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->schedules->delete($schedule);
			DB::commit();
			return ApiResponse::sendResponse(true, [], 'Emploi du temps supprimé.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}
}
