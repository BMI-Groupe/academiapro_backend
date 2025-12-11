<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectStoreRequest;
use App\Http\Requests\SubjectUpdateRequest;
use App\Http\Resources\SubjectResource;
use App\Interfaces\SubjectInterface;
use App\Models\Subject;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
	private SubjectInterface $subjects;

	public function __construct(SubjectInterface $subjects)
	{
		$this->subjects = $subjects;
	}

	public function index(Request $request)
	{
		$filters = [
			'search' => $request->query('search'),
			'per_page' => $request->query('per_page'),
			'school_year_id' => $request->query('school_year_id'),
		];

		$data = $this->subjects->paginate($filters)->through(fn ($s) => new SubjectResource($s));
		return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
	}

	public function store(SubjectStoreRequest $request)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
            $data = $request->validated();
            
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

			$subject = $this->subjects->store($data);
			DB::commit();
			return ApiResponse::sendResponse(true, [new SubjectResource($subject)], 'Matière créée.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Subject $subject)
	{
		return ApiResponse::sendResponse(true, [new SubjectResource($subject->load(['classrooms', 'schoolYear']))], 'Opération effectuée.', 200);
	}

	public function update(SubjectUpdateRequest $request, Subject $subject)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$subject = $this->subjects->update($subject, $request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new SubjectResource($subject)], 'Matière mise à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Subject $subject)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->subjects->delete($subject);
			DB::commit();
			return ApiResponse::sendResponse(true, [], 'Matière supprimée.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}
}
