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
		$currentUser = Auth::user();
		// Autoriser admin et directeur
		if ($currentUser->role !== 'directeur' && $currentUser->role !== 'admin') {
			return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
		}

		DB::beginTransaction();
		try {
			$data = $request->validated();
			
			// Déterminer school_id
			$schoolId = null;
			if ($currentUser->role === 'admin') {
				// Admin doit fournir school_id
				if (empty($data['school_id'])) {
					// Fallback temporaire pour tests si non fourni : première école
					$schoolId = \App\Models\School::first()->id ?? null;
					if (!$schoolId) {
						return ApiResponse::sendResponse(false, [], 'Aucune école trouvée. Un administrateur doit spécifier une école.', 400);
					}
				} else {
					$schoolId = $data['school_id'];
				}
			} else {
				// Directeur utilise son école
				$schoolId = $currentUser->school_id;
				if (!$schoolId) {
					// Cas d'erreur de données utilisateur
					return ApiResponse::sendResponse(false, [], 'Votre compte n\'est associé à aucune école. Contactez l\'administrateur.', 400);
				}
			}

			// Générer un mot de passe aléatoire
			$generatedPassword = \Str::random(8);
			
			// Créer l'utilisateur pour l'enseignant
			$name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: 'Enseignant';
			
			// Utiliser l'email fourni ou générer un email local
			$email = $data['email'] ?? \Str::slug(($data['first_name'] ?? 'user') . '.' . ($data['last_name'] ?? '')) . '.' . time() . '@local.test';
			
			// Vérifier si un utilisateur avec cet email existe déjà
			$existingUser = \App\Models\User::where('email', $email)->first();
			
			if ($existingUser) {
				DB::rollback();
				return ApiResponse::sendResponse(
					false, 
					[], 
					'Un utilisateur avec cet email existe déjà. Veuillez utiliser un autre email.', 
					422
				);
			}
			
			// Vérifier aussi si le téléphone existe déjà (si fourni)
			if (!empty($data['phone'])) {
				$existingPhone = \App\Models\User::where('phone', $data['phone'])->first();
				if ($existingPhone) {
					DB::rollback();
					return ApiResponse::sendResponse(
						false, 
						[], 
						'Un utilisateur avec ce numéro de téléphone existe déjà.', 
						422
					);
				}
			}
			
			$user = \App\Models\User::create([
				'name' => $name,
				'email' => $email,
				'password' => \Hash::make($generatedPassword),
				'role' => 'enseignant',
				'phone' => $data['phone'] ?? null,
				'school_id' => $schoolId, // Assigner l'école à l'utilisateur enseignant
			]);

			$data['user_id'] = $user->id;
			$data['email'] = $email;
			
			// Si Admin, on doit injecter school_id manuellement car le Trait l'ignore pour les admins
			// Si Directeur, le Trait l'aurait mis, mais on peut le mettre explicitement aussi
			$data['school_id'] = $schoolId; 
			
			// Créer l'enseignant
			$teacher = Teacher::create($data)->load(['user', 'classroomSubjectTeachers.classroomSubject.classroom', 'classroomSubjectTeachers.classroomSubject.subject']);
			
			DB::commit();
			
			// Retourner les données avec le mot de passe en clair pour l'envoi d'email
			return ApiResponse::sendResponse(true, [
				'teacher' => new TeacherResource($teacher),
				'credentials' => [
					'email' => $user->email,
					'phone' => $user->phone,
					'password' => $generatedPassword, // Mot de passe en clair pour l'email
					'name' => $name,
				]
			], 'Enseignant créé.', 201);
		} catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
			DB::rollback();
			// Gérer les erreurs de contrainte unique
			if (str_contains($e->getMessage(), 'users_email_unique')) {
				return ApiResponse::sendResponse(
					false, 
					[], 
					'Cet email est déjà utilisé par un autre utilisateur.', 
					422
				);
			} elseif (str_contains($e->getMessage(), 'users_phone_unique')) {
				return ApiResponse::sendResponse(
					false, 
					[], 
					'Ce numéro de téléphone est déjà utilisé par un autre utilisateur.', 
					422
				);
			} elseif (str_contains($e->getMessage(), 'teachers_email_unique')) {
				return ApiResponse::sendResponse(
					false, 
					[], 
					'Cet email est déjà utilisé par un autre enseignant.', 
					422
				);
			}
			
			return ApiResponse::sendResponse(
				false, 
				[], 
				'Une erreur de duplication est survenue : ' . $e->getMessage(), 
				422
			);
		} catch (\Throwable $th) {
			return ApiResponse::rollback($th);
		}
	}

	public function show(Teacher $teacher)
	{
		return ApiResponse::sendResponse(true, [new TeacherResource($teacher->load(['classroomSubjectTeachers.classroomSubject.classroom', 'classroomSubjectTeachers.classroomSubject.subject', 'classroomSubjectTeachers.schoolYear']))], 'Opération effectuée.', 200);
	}

	public function update(TeacherUpdateRequest $request, Teacher $teacher)
	{
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
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
		if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
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
