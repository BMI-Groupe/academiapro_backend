<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomAssignTeacherRequest;
use App\Http\Requests\ClassroomEnrollRequest;
use App\Http\Requests\ClassroomStoreRequest;
use App\Http\Requests\ClassroomSubjectSyncRequest;
use App\Http\Requests\ClassroomUpdateRequest;
use App\Http\Resources\ClassroomResource;
use App\Interfaces\ClassroomInterface;
use App\Models\Section;
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

			$classroom = $this->classrooms->store($data);
			$classroom->load(['subjects', 'enrollments']);
			DB::commit();
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Classe créée.', 201);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Section $classroom)
	{
		$classroom->load(['enrollments', 'subjects', 'classroomTemplate']);
		return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Opération effectuée.', 200);
	}

	public function update(ClassroomUpdateRequest $request, Section $classroom)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$classroom = $this->classrooms->update($classroom, $request->validated());
			$classroom->load(['subjects', 'enrollments', 'classroomTemplate']);
			DB::commit();
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Classe mise à jour.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function destroy(Section $classroom)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
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

	public function enroll(ClassroomEnrollRequest $request, Section $classroom)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			$this->classrooms->enrollStudents(
				$classroom,
				$data['student_ids'],
				$data['school_year_id'],
				$data['enrolled_at'] ?? null
			);
			DB::commit();
			$classroom->load(['enrollments', 'subjects']);
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Elèves inscrits.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}
	public function syncSubjects(ClassroomSubjectSyncRequest $request, Section $classroom)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
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

	public function assignTeachers(ClassroomAssignTeacherRequest $request, Section $classroom)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			$this->classrooms->assignTeachers($classroom, $data['subject_id'], $data['teacher_ids']);
			DB::commit();
			$classroom->load(['subjects', 'enrollments', 'classroomTemplate']);
			return ApiResponse::sendResponse(true, [new ClassroomResource($classroom)], 'Enseignants assignés.', 200);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	/**
	 * Get students with ranking for a specific assignment/exam
	 */
	public function studentsWithRanking(Request $request, Section $classroom)
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

		// Get all students in the section via enrollments
		$sectionId = $classroom->id; // $classroom is a Section instance via route binding
		$students = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
			->where('enrollments.section_id', $sectionId)
            ->where('enrollments.school_year_id', $schoolYearId)
			->select('students.id', 'students.first_name', 'students.last_name', 'students.matricule')
			->get();

		// Calculate average and grades for each student
		$studentsWithGrades = $students->map(function ($student) use ($schoolYearId, $assignmentId, $sectionId) {
			$gradesQuery = DB::table('grades')
				->join('assignments', 'grades.assignment_id', '=', 'assignments.id')
				->leftJoin('subjects', 'assignments.subject_id', '=', 'subjects.id')
				->where('grades.student_id', $student->id)
				->where('assignments.school_year_id', $schoolYearId)
				->where('assignments.section_id', $sectionId);

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



