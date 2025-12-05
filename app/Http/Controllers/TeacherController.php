<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherStoreRequest;
use App\Http\Requests\TeacherUpdateRequest;
use App\Http\Resources\TeacherResource;
use App\Interfaces\TeacherInterface;
use App\Models\Teacher;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeacherController extends Controller
{
	private TeacherInterface $teachers;

	public function __construct(TeacherInterface $teachers)
	{
		$this->teachers = $teachers;
	}

	public function index(Request $request)
	{
		$filters = [
			'search' => $request->query('search'),
			'per_page' => $request->query('per_page'),
			'school_year_id' => $request->query('school_year_id'),
		];

		$data = $this->teachers->paginate($filters)->through(fn ($t) => new TeacherResource($t));
		return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
	}

	public function store(TeacherStoreRequest $request)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$teacher = $this->teachers->store($request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new TeacherResource($teacher)], 'Enseignant créé.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Teacher $teacher)
	{
		return ApiResponse::sendResponse(true, [new TeacherResource($teacher->load(['classrooms', 'subjects']))], 'Opération effectuée.', 200);
	}

	public function update(TeacherUpdateRequest $request, Teacher $teacher)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$teacher = $this->teachers->update($teacher, $request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new TeacherResource($teacher)], 'Enseignant mis à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Teacher $teacher)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->teachers->delete($teacher);
			DB::commit();
			return ApiResponse::sendResponse(true, [], 'Enseignant supprimé.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}
}
