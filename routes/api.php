<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\EvaluationTypeController;
use App\Http\Controllers\ClassroomSubjectController;
use App\Http\Controllers\ClassroomTemplateSubjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDetailController;
use App\Http\Controllers\ClassroomDetailController;
use App\Http\Controllers\TeacherDetailController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('/v1.0.0')->group(function () {
	Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('otp-code', [AuthController::class, 'checkOtpCode']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum'])->group(function () {
		// Auth endpoints
		Route::get('me', [AuthController::class, 'me']);
		Route::get('logout', [AuthController::class, 'logout']);
        Route::post('update-profile', [AuthController::class, 'updateProfile']);

		// Chatbot endpoint (available to all authenticated users)
		Route::post('chatbot/chat', [ChatbotController::class, 'chat']);

		// Classroom parameter binding (must be defined before routes that use it)
		Route::bind('classroom', function ($value) {
			return \App\Models\Section::findOrFail($value);
		});

		// Classroom Template parameter binding
		Route::bind('template', function ($value) {
			return \App\Models\ClassroomTemplate::findOrFail($value);
		});

		// Only the directeur and admin can create user accounts
		Route::middleware(['role:admin,directeur'])->group(function () {
			Route::post('register', [AuthController::class, 'register']);
		});

		// ----------------------------------------------------------------------
		// GROUP 1: READ ACCESS & TEACHER SPECIFICS (Admin, Directeur, Enseignant)
		// ----------------------------------------------------------------------
		Route::middleware(['role:admin,directeur,enseignant'])->group(function () {
			
			// School Years (Read)
			Route::get('school-years/active', [SchoolYearController::class, 'active']);
			Route::apiResource('school-years', SchoolYearController::class)->only(['index', 'show']);

			// Students (Read + Teacher creates grades)
			Route::apiResource('students', StudentController::class)->only(['index', 'show']);
			Route::get('students/{student}/profile', [StudentController::class, 'profile']);
			Route::get('students/{student}/grades', [StudentController::class, 'grades']); // Teacher sees grades
			Route::get('students/{student}/details', [StudentDetailController::class, 'show']);
			Route::get('students/{student}/enrollments', [StudentDetailController::class, 'enrollments']);
			Route::get('students/{student}/assignments', [StudentDetailController::class, 'assignments']);
			Route::get('students/{student}/report-cards', [ReportCardController::class, 'index']);
			Route::get('report-cards/{reportCard}', [ReportCardController::class, 'show']);
			Route::get('report-cards/{reportCard}/download', [ReportCardController::class, 'download']);

			// Evaluation Types (Read)
			Route::apiResource('evaluation-types', EvaluationTypeController::class)->only(['index', 'show']);

			// Grades (Index, Show, STORE for teachers)
			// Teacher can ADD (store) but NOT Update/Delete
			Route::apiResource('grades', GradeController::class)->only(['index', 'show', 'store']);

			// Classrooms (Read)
			Route::apiResource('classrooms', ClassroomController::class)->only(['index', 'show']);
			Route::get('classrooms/{classroom}/ranking', [ClassroomDetailController::class, 'ranking']);
			Route::get('classrooms/{classroom}/subjects', [ClassroomSubjectController::class, 'index']); // Read classroom subjects
			Route::get('classrooms/{classroom}/details', [ClassroomDetailController::class, 'show']);
			Route::get('classrooms/{classroom}/assignments', [ClassroomDetailController::class, 'assignments']);

			// Teachers (Read)
			Route::apiResource('teachers', TeacherController::class)->only(['index', 'show']);
			Route::get('teachers/{teacher}/details', [TeacherDetailController::class, 'show']);
			Route::get('teachers/{teacher}/assignments', [TeacherDetailController::class, 'assignments']);

			// Subjects (Read)
			Route::apiResource('subjects', SubjectController::class)->only(['index', 'show']);

			// Schedules (Read)
			Route::apiResource('schedules', ScheduleController::class)->only(['index', 'show']);

			// Assignments (Read)
			Route::apiResource('assignments', AssignmentController::class)->only(['index', 'show']);
		});

		// ----------------------------------------------------------------------
		// GROUP 2: MANAGEMENT & FINANCE (Admin, Directeur)
		// ----------------------------------------------------------------------
		Route::middleware(['role:admin,directeur'])->group(function () {
			
			// Dashboard Stats
			Route::get('dashboard/stats', [DashboardController::class, 'stats']);

			// CRUD Operations
			Route::apiResource('students', StudentController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('teachers', TeacherController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('subjects', SubjectController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('classrooms', ClassroomController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('school-years', SchoolYearController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('evaluation-types', EvaluationTypeController::class)->only(['store', 'update', 'destroy']);
			
			// Schedules CRUD
			Route::apiResource('schedules', ScheduleController::class)->only(['store', 'update', 'destroy']);

			// Assignments CRUD
			Route::apiResource('assignments', AssignmentController::class)->only(['store', 'update', 'destroy']);

			// Grades Update/Delete (Restricted to Admin/Dir)
			Route::apiResource('grades', GradeController::class)->only(['update', 'destroy']);

			// Classroom Management specifics
			Route::post('classrooms/{classroom}/enrollments', [ClassroomController::class, 'enroll']);
			Route::post('classrooms/{classroom}/subjects', [ClassroomSubjectController::class, 'store']);
			Route::put('classrooms/{classroom}/subjects/{subject}', [ClassroomSubjectController::class, 'update']);
			Route::delete('classrooms/{classroom}/subjects/{subject}', [ClassroomSubjectController::class, 'destroy']);
			Route::post('classrooms/{classroom}/subjects/copy', [ClassroomSubjectController::class, 'copy']);
			Route::put('classrooms/{classroom}/subjects', [ClassroomController::class, 'syncSubjects']); // Deprecated?
			Route::put('classrooms/{classroom}/assign-teachers', [ClassroomController::class, 'assignTeachers']); // Deprecated?
			
			// Classroom Template Subjects Management (Manage subjects at template level)
			Route::get('classroom-templates/{template}/subjects', [ClassroomTemplateSubjectController::class, 'index']);
			Route::post('classroom-templates/{template}/subjects', [ClassroomTemplateSubjectController::class, 'store']);
			Route::put('classroom-templates/{template}/subjects/{subject}', [ClassroomTemplateSubjectController::class, 'update']);
			Route::delete('classroom-templates/{template}/subjects/{subject}', [ClassroomTemplateSubjectController::class, 'destroy']);
			
			// Report Card Generation ? Assuming Director generates them officially
			Route::post('students/{student}/report-cards/generate', [ReportCardController::class, 'generate']);
			Route::put('report-cards/{reportCard}', [ReportCardController::class, 'update']);

			// Student Enrollment Management
			Route::post('students/{student}/reassign-classroom', [StudentDetailController::class, 'reassignClassroom']);

			// Teacher Assignment Management
			Route::post('teachers/{teacher}/assign-section-subject', [TeacherDetailController::class, 'assignSectionSubject']);
			Route::post('teachers/{teacher}/reassign-section-subject', [TeacherDetailController::class, 'reassignSectionSubject']);
			Route::delete('teachers/{teacher}/assignments/{assignment}', [TeacherDetailController::class, 'removeAssignment']);

			// FINANCE & PAYMENTS (Strictly Admin/Directeur)
			Route::get('students/{student}/payment-details', [StudentController::class, 'payments']);
			Route::get('students/{student}/balance', [PaymentController::class, 'getStudentBalance']);
			Route::get('students/{student}/payments', [PaymentController::class, 'getStudentPayments']);
			Route::get('payments', [PaymentController::class, 'index']);
			Route::post('payments', [PaymentController::class, 'store']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);
			Route::get('payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt']);

            // USER MANAGEMENT (Admin & Directeur)
            Route::apiResource('users', \App\Http\Controllers\UserController::class)->only(['index', 'destroy', 'store']);
		});

		// ----------------------------------------------------------------------
		// GROUP 3: SYSTEM ADMINISTRATION (Admin Only)
		// ----------------------------------------------------------------------
		Route::middleware(['role:admin'])->group(function () {
			// SCHOOLS Management
            Route::apiResource('schools', SchoolController::class);
		});
    });
});
