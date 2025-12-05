<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ReportCardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::prefix('/v1.0.0')->group(function () {
	Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('otp-code', [AuthController::class, 'checkOtpCode']);

    Route::middleware(['auth:sanctum'])->group(function () {
		// Auth endpoints
		Route::get('me', [AuthController::class, 'me']);
		Route::get('logout', [AuthController::class, 'logout']);

		// Route::get('users', [UserController::class, 'index']);
		// Only the directeur can create user accounts (teachers, other directors)
		Route::middleware(['role:directeur'])->group(function () {
			Route::post('register', [AuthController::class, 'register']);
		});

		// Eleves - accès admin et enseignant
		Route::middleware(['role:directeur,enseignant'])->group(function () {
			// active school year
			Route::get('school-years/active', [\App\Http\Controllers\SchoolYearController::class, 'active']);
			Route::apiResource('students', StudentController::class);
			
			// Student profile and grades
			Route::get('students/{student}/profile', [StudentController::class, 'profile']);
			Route::get('students/{student}/grades', [StudentController::class, 'grades']);
			
			Route::apiResource('classrooms', ClassroomController::class)->only(['index', 'show']);
			
			// Classroom ranking
			Route::get('classrooms/{classroom}/ranking', [ClassroomController::class, 'studentsWithRanking']);
			
			Route::apiResource('teachers', TeacherController::class)->only(['index', 'show']);
			Route::apiResource('subjects', SubjectController::class)->only(['index', 'show']);
			Route::apiResource('grades', GradeController::class)->only(['index', 'show', 'store', 'update']);
			Route::apiResource('assignments', \App\Http\Controllers\AssignmentController::class)->only(['index', 'show']);
			Route::apiResource('school-years', \App\Http\Controllers\SchoolYearController::class)->only(['index', 'show']);
			Route::apiResource('schedules', ScheduleController::class)->only(['index', 'show']);
			Route::apiResource('evaluation-types', \App\Http\Controllers\EvaluationTypeController::class)->only(['index', 'show']);
			
			// Report cards
			Route::get('students/{student}/report-cards', [ReportCardController::class, 'index']);
			Route::post('students/{student}/report-cards/generate', [ReportCardController::class, 'generate']);
			Route::get('report-cards/{reportCard}/download', [ReportCardController::class, 'download']);
		});

		Route::middleware(['role:directeur'])->group(function () {
			Route::apiResource('classrooms', ClassroomController::class)->only(['store', 'update', 'destroy']);
			Route::post('classrooms/{classroom}/enrollments', [ClassroomController::class, 'enroll']);
			
			// Gestion des matières par classe (avec année scolaire)
			Route::get('classrooms/{classroom}/subjects', [\App\Http\Controllers\ClassroomSubjectController::class, 'index']);
			Route::post('classrooms/{classroom}/subjects', [\App\Http\Controllers\ClassroomSubjectController::class, 'store']);
			Route::put('classrooms/{classroom}/subjects/{subject}', [\App\Http\Controllers\ClassroomSubjectController::class, 'update']);
			Route::delete('classrooms/{classroom}/subjects/{subject}', [\App\Http\Controllers\ClassroomSubjectController::class, 'destroy']);
			Route::post('classrooms/{classroom}/subjects/copy', [\App\Http\Controllers\ClassroomSubjectController::class, 'copy']);
			
			// Anciennes routes (à déprécier)
			Route::put('classrooms/{classroom}/subjects', [ClassroomController::class, 'syncSubjects']);
			Route::put('classrooms/{classroom}/assign-teachers', [ClassroomController::class, 'assignTeachers']);
			Route::apiResource('teachers', TeacherController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('subjects', SubjectController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('assignments', \App\Http\Controllers\AssignmentController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('grades', GradeController::class)->only(['destroy']);
			Route::apiResource('schedules', ScheduleController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('school-years', \App\Http\Controllers\SchoolYearController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('evaluation-types', \App\Http\Controllers\EvaluationTypeController::class)->only(['store', 'update', 'destroy']);
		});
    });
});
