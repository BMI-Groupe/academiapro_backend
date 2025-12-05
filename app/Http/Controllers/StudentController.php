<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentStoreRequest;
use App\Http\Requests\StudentUpdateRequest;
use App\Http\Resources\StudentResource;
use App\Interfaces\StudentInterface;
use App\Models\Student;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
	private StudentInterface $students;

	public function __construct(StudentInterface $students)
	{
		$this->students = $students;
	}

	public function index(Request $request)
	{
		$filters = [
			'search' => $request->query('search'),
			'per_page' => $request->query('per_page'),
			'school_year_id' => $request->query('school_year_id'),
		];

		$data = $this->students->paginate($filters)->through(fn ($s) => new StudentResource($s));
		return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
	}

	public function store(StudentStoreRequest $request)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$student = $this->students->store($request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new StudentResource($student)], 'Elève créé.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Student $student)
	{
		return ApiResponse::sendResponse(true, [new StudentResource($student->load('subjects'))], 'Opération effectuée.', 200);
	}

	public function update(StudentUpdateRequest $request, Student $student)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$student = $this->students->update($student, $request->validated());
			DB::commit();
			return ApiResponse::sendResponse(true, [new StudentResource($student)], 'Elève mis à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Student $student)
	{
		if (Auth::user()->role !== 'directeur') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$this->students->delete($student);
			DB::commit();
			return ApiResponse::sendResponse(true, [], 'Elève supprimé.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	/**
	 * Get student profile with enrollment history
	 */
	public function profile(Student $student)
	{
		$student->load(['classroom', 'subjects']);
		
		// Get all school years the student has been enrolled in
		$schoolYears = DB::table('grades')
			->join('assignments', 'grades.assignment_id', '=', 'assignments.id')
			->join('school_years', 'assignments.school_year_id', '=', 'school_years.id')
			->where('grades.student_id', $student->id)
			->select('school_years.*')
			->distinct()
			->get();

		return ApiResponse::sendResponse(true, [
			'student' => new StudentResource($student),
			'school_years' => $schoolYears
		], 'Profil récupéré.', 200);
	}

	/**
	 * Get student grades for a specific school year
	 */
	public function grades(Request $request, Student $student)
	{
		$schoolYearId = $request->query('school_year_id');
		
		if (!$schoolYearId) {
			return ApiResponse::sendResponse(false, [], 'L\'année scolaire est requise.', 400);
		}

		// Get all grades for the student in the specified school year
		$grades = DB::table('grades')
			->join('assignments', 'grades.assignment_id', '=', 'assignments.id')
			->leftJoin('subjects', 'assignments.subject_id', '=', 'subjects.id')
			->where('grades.student_id', $student->id)
			->where('assignments.school_year_id', $schoolYearId)
			->select(
				'grades.*',
				'assignments.title as assignment_title',
				'assignments.type as assignment_type',
				'assignments.max_score',
				'subjects.name as subject_name',
				'subjects.id as subject_id'
			)
			->orderBy('grades.graded_at', 'desc')
			->get();

		// Calculate statistics
		$average = $grades->avg('score');
		
		// Get rank (simplified - could be improved with proper ranking logic)
		$classroomId = $student->classroom_id;
		if ($classroomId) {
			$studentsAverages = DB::table('grades')
				->join('assignments', 'grades.assignment_id', '=', 'assignments.id')
				->join('students', 'grades.student_id', '=', 'students.id')
				->where('students.classroom_id', $classroomId)
				->where('assignments.school_year_id', $schoolYearId)
				->select('students.id', DB::raw('AVG(grades.score) as avg_score'))
				->groupBy('students.id')
				->orderBy('avg_score', 'desc')
				->get();

			$rank = $studentsAverages->search(function ($item) use ($student) {
				return $item->id == $student->id;
			}) + 1;

			$totalStudents = $studentsAverages->count();
		} else {
			$rank = null;
			$totalStudents = null;
		}

		return ApiResponse::sendResponse(true, [
			'grades' => $grades,
			'statistics' => [
				'average' => round($average, 2),
				'rank' => $rank,
				'total_students' => $totalStudents
			]
		], 'Notes récupérées.', 200);
	}
}


