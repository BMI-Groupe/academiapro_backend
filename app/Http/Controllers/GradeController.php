<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeStoreRequest;
use App\Http\Requests\GradeUpdateRequest;
use App\Http\Resources\GradeResource;
use App\Interfaces\GradeInterface;
use App\Models\Grade;
use App\Models\Assignment;
use App\Models\Teacher;
use App\Responses\ApiResponse;
use App\Services\TeacherPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
	private GradeInterface $grades;
	private TeacherPermissionService $permissionService;

	public function __construct(GradeInterface $grades, TeacherPermissionService $permissionService)
	{
		$this->grades = $grades;
		$this->permissionService = $permissionService;
	}

	public function index(Request $request)
	{
		$filters = [
			'student_id' => $request->query('student_id'),
			'subject_id' => $request->query('subject_id'),
			'classroom_id' => $request->query('classroom_id'),
			'school_year_id' => $request->query('school_year_id'),
			'assignment_type' => $request->query('assignment_type'),
			'per_page' => $request->query('per_page'),
		];

		$data = $this->grades->paginate($filters)->through(fn ($g) => new GradeResource($g));
		return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
	}

	public function store(GradeStoreRequest $request)
	{
		// Vérifier que l'utilisateur est un enseignant
		if (!in_array(Auth::user()->role, ['admin', 'directeur', 'enseignant'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		// Charger l'assignment pour vérifier les permissions
		$assignment = Assignment::findOrFail($request->assignment_id);

		// Si c'est un enseignant, vérifier qu'il peut noter ce devoir
		if (Auth::user()->role === 'enseignant') {
			$teacher = Teacher::where('user_id', Auth::id())->first();
			
			if (!$teacher || !$this->permissionService->canGradeAssignment($teacher, $assignment)) {
				return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à noter ce devoir. Vérifiez vos assignations.', 403);
			}
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			$data['graded_by'] = Auth::user()->role === 'enseignant' ? Teacher::where('user_id', Auth::id())->first()->id : null;
			$data['graded_at'] = $data['graded_at'] ?? now();
            
            // Assigner school_id depuis l'assignment
            $data['school_id'] = $assignment->school_id;
			
			$grade = $this->grades->store($data);
			DB::commit();
			return ApiResponse::sendResponse(true, [new GradeResource($grade)], 'Note enregistrée.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Grade $grade)
	{
		return ApiResponse::sendResponse(true, [new GradeResource($grade->load(['student', 'assignment.subject', 'assignment.classroom', 'assignment.schoolYear', 'grader']))], 'Opération effectuée.', 200);
	}

	public function update(GradeUpdateRequest $request, Grade $grade)
	{
		// Vérifier que l'utilisateur est un enseignant
		if (!in_array(Auth::user()->role, ['admin', 'directeur', 'enseignant'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		// Charger l'assignment pour vérifier les permissions
		$assignment = $grade->assignment;

		// Si c'est un enseignant, vérifier qu'il peut noter ce devoir
		if (Auth::user()->role === 'enseignant') {
			$teacher = Teacher::where('user_id', Auth::id())->first();
			
			if (!$teacher || !$this->permissionService->canGradeAssignment($teacher, $assignment)) {
				return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à modifier cette note.', 403);
			}
		}

		DB::beginTransaction();
		try {
			$grade = $this->grades->update($grade, $request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new GradeResource($grade)], 'Note mise à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Grade $grade)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur', 'enseignant'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->grades->delete($grade);
			DB::commit();
			return ApiResponse::sendResponse(true, [], 'Note supprimée.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}
}
