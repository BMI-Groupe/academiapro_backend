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
			'classroom_id' => $request->query('classroom_id') ?? $request->query('section_id'), // Support les deux pour compatibilité
			'section_id' => $request->query('section_id') ?? $request->query('classroom_id'),
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
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
            $data = $request->validated();
            
            // Utiliser section_id si présent, sinon classroom_id (compatibilité)
            if (isset($data['section_id'])) {
                $data['section_id'] = $data['section_id'];
            } elseif (isset($data['classroom_id'])) {
                $data['section_id'] = $data['classroom_id'];
                unset($data['classroom_id']);
            }
            
            if (!isset($data['school_id'])) {
                $user = Auth::user();
                if ($user && $user->school_id) {
                    $data['school_id'] = $user->school_id;
                } else {
                     $firstSchool = \App\Models\School::first();
                     if ($firstSchool) {
                         $data['school_id'] = $firstSchool->id;
                     }
                }
            }

			$schedule = $this->schedules->store($data);
			DB::commit();
			return ApiResponse::sendResponse(true, [new ScheduleResource($schedule)], 'Emploi du temps créé.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Schedule $schedule)
	{
		return ApiResponse::sendResponse(true, [new ScheduleResource($schedule->load(['section.classroomTemplate', 'subject', 'teacher', 'schoolYear']))], 'Opération effectuée.', 200);
	}

	public function update(ScheduleUpdateRequest $request, Schedule $schedule)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			// Utiliser section_id si présent, sinon classroom_id (compatibilité)
			if (isset($data['section_id'])) {
				$data['section_id'] = $data['section_id'];
			} elseif (isset($data['classroom_id'])) {
				$data['section_id'] = $data['classroom_id'];
				unset($data['classroom_id']);
			}
			$schedule = $this->schedules->update($schedule, $data);
			DB::commit();
			return ApiResponse::sendResponse(true, [new ScheduleResource($schedule)], 'Emploi du temps mis à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Schedule $schedule)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
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
