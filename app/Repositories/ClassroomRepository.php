<?php

namespace App\Repositories;

use App\Interfaces\ClassroomInterface;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\StudentSubject;
use App\Models\ClassroomTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ClassroomRepository implements ClassroomInterface
{
	public function paginate(array $filters = []): LengthAwarePaginator
	{
		$query = Section::query()->with(['classroomTemplate', 'schoolYear']);

		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
					->orWhere('code', 'like', "%{$search}%")
					->orWhereHas('classroomTemplate', function ($tq) use ($search) {
						$tq->where('name', 'like', "%{$search}%")
							->orWhere('code', 'like', "%{$search}%")
							->orWhere('level', 'like', "%{$search}%");
					});
			});
		}

		if (!empty($filters['cycle'])) {
			$query->whereHas('classroomTemplate', function ($q) use ($filters) {
				$q->where('cycle', $filters['cycle']);
			});
		}

		if (!empty($filters['level'])) {
			$query->whereHas('classroomTemplate', function ($q) use ($filters) {
				$q->where('level', $filters['level']);
			});
		}

		if (!empty($filters['school_year_id'])) {
			$query->where('school_year_id', $filters['school_year_id']);
		}

		return $query->orderBy('school_year_id')->orderBy('name')->paginate($filters['per_page'] ?? 15);
	}

	public function store(array $data): Section
	{
		// Si classroom_template_id n'est pas fourni, créer ou trouver le template
		if (empty($data['classroom_template_id'])) {
			$template = \App\Models\ClassroomTemplate::firstOrCreate(
				[
					'school_id' => $data['school_id'] ?? auth()->user()->school_id,
					'code' => $data['code'] ?? $data['name'],
				],
				[
					'name' => $data['name'],
					'cycle' => $data['cycle'] ?? 'college',
					'level' => $data['level'] ?? $data['name'],
					'tuition_fee' => $data['tuition_fee'] ?? 0,
					'is_active' => true,
				]
			);
			$data['classroom_template_id'] = $template->id;
		}

		// Générer le code de section si non fourni
		if (empty($data['code'])) {
			$year = \App\Models\SchoolYear::find($data['school_year_id']);
			$yearLabel = $year ? $year->label : date('Y');
			$data['code'] = ($data['name'] ?? $template->name) . '-' . $yearLabel;
		}

		$section = Section::create($data);

		// Hériter automatiquement des matières du template pour cette année scolaire
		if (isset($data['school_year_id']) && $template) {
			$this->inheritSubjectsFromTemplate($section, $template, $data['school_year_id']);
		}

		return $section;
	}

	public function update(Section $section, array $data): Section
	{
		$section->update($data);
		return $section->fresh(['classroomTemplate', 'schoolYear']);
	}

	public function delete(Section $section): void
	{
		$section->delete();
	}

	public function enrollStudents(Section $section, array $studentIds, int $schoolYearId, ?string $enrolledAt = null): void
	{
		$date = $enrolledAt ? Carbon::parse($enrolledAt)->toDateString() : now()->toDateString();

		foreach ($studentIds as $studentId) {
			// Vérifier si l'élève a déjà une inscription active pour cette année
			$existingActiveEnrollment = Enrollment::where('student_id', $studentId)
				->where('school_year_id', $schoolYearId)
				->where('status', 'active')
				->first();

			if ($existingActiveEnrollment) {
				if ($existingActiveEnrollment->section_id !== $section->id) {
					// Si déjà inscrit ailleurs, marquer l'ancienne comme 'completed' et créer une nouvelle pour le redoublement
					$existingActiveEnrollment->update(['status' => 'completed']);
					
					// Créer une nouvelle inscription pour le redoublement
					Enrollment::create([
						'student_id' => $studentId,
						'section_id' => $section->id,
						'school_year_id' => $schoolYearId,
						'enrolled_at' => $date,
						'status' => 'active',
					]);
				}
				// Si déjà dans la même section, ne rien faire
			} else {
				// Créer une nouvelle inscription
				Enrollment::updateOrCreate(
					[
						'student_id' => $studentId,
						'school_year_id' => $schoolYearId,
						'status' => 'active',
					],
					[
						'section_id' => $section->id,
						'enrolled_at' => $date,
					]
				);
			}
		}

		$enrollments = $section->enrollments()
			->whereIn('student_id', $studentIds)
			->where('school_year_id', $schoolYearId)
			->get();

		$this->syncStudentSubjectsForEnrollments($section, $enrollments, $schoolYearId);
	}

	public function syncSubjects(Section $section, array $subjectIds): void
	{
		$section->subjects()->sync($subjectIds);

		// Synchronisation optionnelle avec StudentSubject (si la table existe)
		if (!class_exists(StudentSubject::class)) {
			return;
		}

		try {
			if (empty($subjectIds)) {
				StudentSubject::where('classroom_id', $section->id)->delete();
				return;
			}

			// Remove obsolete subject associations for students of this section
			StudentSubject::where('classroom_id', $section->id)
				->whereNotIn('subject_id', $subjectIds)
				->delete();

			// Récupérer toutes les inscriptions de cette section avec leur school_year_id
			$enrollments = $section->enrollments()
				->with('schoolYear')
				->get();
			
			// Grouper par année scolaire et synchroniser pour chaque année
			$enrollmentsByYear = $enrollments->groupBy('school_year_id');
			foreach ($enrollmentsByYear as $schoolYearId => $yearEnrollments) {
				$this->syncStudentSubjectsForEnrollments($section, $yearEnrollments, $schoolYearId);
			}
		} catch (\Exception $e) {
			\Log::warning('Erreur lors de la synchronisation des matières de la section', [
				'section_id' => $section->id,
				'error' => $e->getMessage()
			]);
		}
	}

	public function assignTeachers(Section $section, int $subjectId, array $teacherIds): void
	{
		// ensure the subject is registered for the section
		$section->subjects()->syncWithoutDetaching([$subjectId]);

		// Trouver le section_subject_id
		$sectionSubject = \App\Models\SectionSubject::where('section_id', $section->id)
			->where('subject_id', $subjectId)
			->first();

		if (!$sectionSubject) {
			return;
		}

		$existing = DB::table('section_subject_teachers')
			->where('section_subject_id', $sectionSubject->id)
			->pluck('teacher_id')
			->toArray();

		$toRemove = array_diff($existing, $teacherIds);

		if (!empty($toRemove)) {
			DB::table('section_subject_teachers')
				->where('section_subject_id', $sectionSubject->id)
				->whereIn('teacher_id', $toRemove)
				->delete();
		}

		if (!empty($teacherIds)) {
			$now = now();
			$records = [];
			foreach ($teacherIds as $teacherId) {
				$records[] = [
					'section_subject_id' => $sectionSubject->id,
					'teacher_id' => $teacherId,
					'school_year_id' => $section->school_year_id,
					'created_at' => $now,
					'updated_at' => $now,
				];
			}

			DB::table('section_subject_teachers')->upsert(
				$records,
				['section_subject_id', 'teacher_id', 'school_year_id'],
				['updated_at']
			);
		}
	}

	/**
	 * Hériter automatiquement des matières du template pour une section
	 */
	private function inheritSubjectsFromTemplate(Section $section, ClassroomTemplate $template, int $schoolYearId): void
	{
		// Récupérer les matières du template pour cette année scolaire spécifique
		$templateSubjects = $template->templateSubjects()
			->where('school_year_id', $schoolYearId)
			->with('subject')
			->get();

		if ($templateSubjects->isEmpty()) {
			return; // Pas de matières à hériter pour cette année
		}

		// Créer les SectionSubject pour chaque matière du template
		foreach ($templateSubjects as $templateSubject) {
			\App\Models\SectionSubject::updateOrCreate(
				[
					'section_id' => $section->id,
					'subject_id' => $templateSubject->subject_id,
					'school_year_id' => $schoolYearId,
				],
				[
					'coefficient' => $templateSubject->coefficient,
				]
			);
		}
	}

	private function syncStudentSubjectsForEnrollments(Section $section, Collection $enrollments, ?int $schoolYearId = null): void
	{
		// Vérifier si le modèle StudentSubject existe et si la table est disponible
		if (!class_exists(StudentSubject::class)) {
			return;
		}

		try {
			$subjectIds = $section->subjects()->pluck('subjects.id')->toArray();

			if (empty($subjectIds) || $enrollments->isEmpty()) {
				return;
			}

			$now = now();
			$records = [];

			foreach ($enrollments as $enrollment) {
				// Utiliser le school_year_id de l'enrollment ou celui fourni
				$yearId = $schoolYearId ?? $enrollment->school_year_id;
				
				// Récupérer le label de l'année scolaire pour StudentSubject (qui utilise school_year string)
				$schoolYear = \App\Models\SchoolYear::find($yearId);
				$schoolYearLabel = $schoolYear ? $schoolYear->label : null;
				
				if (!$schoolYearLabel) {
					continue; // Skip si pas d'année trouvée
				}
				
				foreach ($subjectIds as $subjectId) {
					$records[] = [
						'student_id' => $enrollment->student_id,
						'subject_id' => $subjectId,
						'classroom_id' => $section->id, // StudentSubject utilise encore classroom_id
						'school_year' => $schoolYearLabel, // StudentSubject utilise school_year (string)
						'created_at' => $now,
						'updated_at' => $now,
					];
				}
			}

			if (!empty($records)) {
				StudentSubject::upsert(
					$records,
					['student_id', 'subject_id', 'classroom_id', 'school_year'],
					['updated_at']
				);
			}
		} catch (\Exception $e) {
			// Log l'erreur mais ne bloque pas l'inscription
			\Log::warning('Erreur lors de la synchronisation des matières des élèves', [
				'section_id' => $section->id,
				'school_year_id' => $schoolYearId,
				'error' => $e->getMessage()
			]);
		}
	}
}

