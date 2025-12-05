<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomAssignTeacherRequest;
use App\Http\Requests\ClassroomEnrollRequest;
use App\Http\Requests\ClassroomStoreRequest;
use App\Http\Requests\ClassroomSubjectSyncRequest;
use App\Http\Requests\ClassroomUpdateRequest;
use App\Http\Resources\ClassroomResource;
use App\Interfaces\ClassroomInterface;
use App\Models\Classroom;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassroomController extends Controller
{
	public function __construct(private ClassroomInterface $classrooms)
	{
	}

	public function index(Request $request)
	{
		$filters = [
			'search' => $request->query('search'),
			'cycle' => $request->query('cycle'),
			'level' => $request->query('level'),
			'per_page' => $request->query('per_page'),
			'school_year_id' => $request->query('school_year_id'),
		];

		$data = $this->classrooms
			->paginate($filters)
			->through(fn ($c) => new ClassroomResource($c));

		return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
	}

	public function store(ClassroomStoreRequest $request)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$classroom = $this->classrooms->store($request->validated());
			$classroom->load(['subjects', 'enrollments']);
			DB::commit();
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Classe créée.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Classroom $classroom)
	{
		$classroom->load(['enrollments', 'subjects']);
		return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Opération effectuée.', 200);
	}

	public function update(ClassroomUpdateRequest $request, Classroom $classroom)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$classroom = $this->classrooms->update($classroom, $request->validated());
			$classroom->load(['subjects', 'enrollments']);
			DB::commit();
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Classe mise à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Classroom $classroom)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->classrooms->delete($classroom);
			DB::commit();
			return ApiResponse::sendResponse(true, [], 'Classe supprimée.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function enroll(ClassroomEnrollRequest $request, Classroom $classroom)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			$this->classrooms->enrollStudents(
				$classroom,
				$data['student_ids'],
				$data['school_year'],
				$data['enrolled_at'] ?? null
			);
			DB::commit();
			$classroom->load(['enrollments', 'subjects']);
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Elèves inscrits.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}
	public function syncSubjects(ClassroomSubjectSyncRequest $request, Classroom $classroom)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->classrooms->syncSubjects($classroom, $request->validated()['subject_ids']);
			DB::commit();
			$classroom->load(['subjects', 'enrollments']);
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Matières associées à la classe.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function assignTeachers(ClassroomAssignTeacherRequest $request, Classroom $classroom)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			$this->classrooms->assignTeachers($classroom, $data['subject_id'], $data['teacher_ids']);
			DB::commit();
			$classroom->load(['subjects', 'enrollments']);
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Enseignants assignés.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	/**
	 * Get students with ranking for a specific assignment/exam
	 */
	public function studentsWithRanking(Request $request, Classroom $classroom)
	{
		$schoolYearId = $request->query('school_year_id');
		$assignmentId = $request->query('assignment_id');

		if (!$schoolYearId) {
			return ApiResponse::sendResponse(false, [], 'L\'année scolaire est requise.', 400);
		}

		// Get classroom info
		$classroom->load('subjects');

		// Get assignment info if specified
		$assignment = null;
		if ($assignmentId) {
			$assignment = DB::table('assignments')
				->where('id', $assignmentId)
				->first();
		}

		// Get all students in the classroom
		$students = DB::table('students')
			->where('classroom_id', $classroom->id)
			->select('id', 'first_name', 'last_name', 'registration_number')
			->get();

		// Calculate average and grades for each student
		$studentsWithGrades = $students->map(function ($student) use ($schoolYearId, $assignmentId) {
			$gradesQuery = DB::table('grades')
				->join('assignments', 'grades.assignment_id', '=', 'assignments.id')
				->leftJoin('subjects', 'assignments.subject_id', '=', 'subjects.id')
				->where('grades.student_id', $student->id)
				->where('assignments.school_year_id', $schoolYearId);

			if ($assignmentId) {
				$gradesQuery->where('assignments.id', $assignmentId);
			}

			$grades = $gradesQuery
				->select(
					'grades.score',
					'subjects.name as subject_name',
					'subjects.id as subject_id'
				)
				->get();

			$average = $grades->avg('score') ?? 0;

			// Group grades by subject
			$gradesBySubject = $grades->groupBy('subject_id')->map(function ($subjectGrades) {
				return [
					'subject' => $subjectGrades->first()->subject_name ?? 'Global',
					'score' => $subjectGrades->avg('score')
				];
			})->values();

			return [
				'student' => $student,
				'average' => round($average, 2),
				'grades' => $gradesBySubject
			];
		});

		// Sort by average (descending) and assign ranks
		$ranked = $studentsWithGrades->sortByDesc('average')->values();
		$rankedWithPosition = $ranked->map(function ($item, $index) {
			$item['rank'] = $index + 1;
			return $item;
		});

		return ApiResponse::sendResponse(true, [
			'classroom' => new ClassroomResource($classroom),
			'school_year_id' => $schoolYearId,
			'assignment' => $assignment,
			'students' => $rankedWithPosition
		], 'Classement récupéré.', 200);
	}
}



