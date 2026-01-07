<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignmentStoreRequest;
use App\Http\Requests\AssignmentUpdateRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\SchoolYear;
use App\Models\SectionSubject;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Assignment::with(['section.classroomTemplate', 'subject', 'schoolYear', 'creator']);

        // Recherche textuelle
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('section', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('subject', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        // Filtres optionnels
        if ($request->has('classroom_id') || $request->has('section_id')) {
            $sectionId = $request->section_id ?? $request->classroom_id;
            $query->where('section_id', $sectionId);
        }

        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->has('school_year_id')) {
            $query->where('school_year_id', $request->school_year_id);
        }

        if ($request->has('evaluation_type_id')) {
            $query->where('evaluation_type_id', $request->evaluation_type_id);
        }

        $perPage = $request->get('per_page', 15);
        $data = $query->paginate($perPage)->through(fn ($a) => new AssignmentResource($a));

        return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
    }

    public function store(AssignmentStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            // Récupérer l'année scolaire active ou celle fournie
            $schoolYearId = $request->school_year_id;
            $activeYear = $schoolYearId 
                ? SchoolYear::find($schoolYearId)
                : SchoolYear::where('is_active', true)->first();

            if (!$activeYear) {
                return ApiResponse::sendResponse(false, [], 'Aucune année scolaire trouvée.', 422);
            }

            $data = $request->validated();
            $applyToAllSections = $request->boolean('apply_to_all_sections', false);
            $applyToAllSubjects = $request->boolean('apply_to_all_subjects', false);
            
            // Déterminer les sections cibles
            $targetSections = [];
            if ($applyToAllSections || (!$request->section_id && !$request->classroom_id)) {
                // Toutes les sections de l'année scolaire
                $targetSections = \App\Models\Section::where('school_year_id', $activeYear->id)->get();
            } else {
                $sectionId = $request->section_id ?? $request->classroom_id;
                if ($sectionId) {
                    $section = \App\Models\Section::find($sectionId);
                    if ($section) {
                        $targetSections = collect([$section]);
                    }
                }
            }

            if ($targetSections->isEmpty()) {
                return ApiResponse::sendResponse(false, [], 'Aucune section trouvée pour créer l\'examen.', 422);
            }

            // Si une matière spécifique est fournie, vérifier qu'elle est assignée aux sections
            if ($request->subject_id && !$applyToAllSubjects) {
                foreach ($targetSections as $section) {
                    $sectionSubject = SectionSubject::where('section_id', $section->id)
                        ->where('subject_id', $request->subject_id)
                        ->where('school_year_id', $activeYear->id)
                        ->first();

                    if (!$sectionSubject) {
                        return ApiResponse::sendResponse(false, [], "La matière n'est pas assignée à la section {$section->name}.", 422);
                    }
                }
            }

            // Préparer les données de base
            $baseData = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'max_score' => $data['max_score'],
                'passing_score' => $data['passing_score'],
                'total_score' => $data['total_score'],
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'],
                'subject_id' => $applyToAllSubjects ? null : ($data['subject_id'] ?? null),
                'school_year_id' => $activeYear->id,
                'period' => $data['period'] ?? null,
                'created_by' => Auth::id(),
            ];

            if (!isset($baseData['school_id'])) {
                $user = Auth::user();
                if ($user && $user->school_id) {
                    $baseData['school_id'] = $user->school_id;
                } else {
                     $firstSchool = \App\Models\School::first();
                     if ($firstSchool) {
                         $baseData['school_id'] = $firstSchool->id;
                     }
                }
            }

            // Créer un assignment pour chaque section
            $createdAssignments = [];
            foreach ($targetSections as $section) {
                $assignmentData = array_merge($baseData, ['section_id' => $section->id]);
                $createdAssignments[] = Assignment::create($assignmentData);
            }

            DB::commit();
            
            $message = count($createdAssignments) > 1 
                ? count($createdAssignments) . ' examens créés avec succès.'
                : 'Examen créé avec succès.';
            
            return ApiResponse::sendResponse(
                true, 
                array_map(fn($a) => new AssignmentResource($a), $createdAssignments), 
                $message, 
                201
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function show(Assignment $assignment)
    {
        return ApiResponse::sendResponse(
            true,
            [new AssignmentResource($assignment->load(['section.classroomTemplate', 'subject', 'schoolYear', 'creator']))],
            'Opération effectuée.',
            200
        );
    }

    public function update(AssignmentUpdateRequest $request, Assignment $assignment)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $applyToAllSections = $request->boolean('apply_to_all_sections', false);
            $applyToAllSubjects = $request->boolean('apply_to_all_subjects', false);
            
            // Si on veut appliquer à toutes les sections, c'est une opération complexe
            // On ne peut pas modifier un assignment existant pour qu'il s'applique à toutes les sections
            // On doit soit créer de nouveaux assignments, soit supprimer et recréer
            // Pour l'instant, on garde la logique simple : on met à jour l'assignment existant
            // Si apply_to_all_sections est true, on ignore cette option en mode update
            // (c'est une limitation : pour changer vers "toutes les sections", il faut supprimer et recréer)
            
            // Déterminer la section cible
            $targetSectionId = null;
            if ($applyToAllSections) {
                // En mode update, on ne peut pas changer un assignment unique en "toutes les sections"
                // On garde la section actuelle
                $targetSectionId = $assignment->section_id;
            } else {
                $targetSectionId = $request->section_id ?? $request->classroom_id ?? $assignment->section_id;
            }

            if (!$targetSectionId) {
                return ApiResponse::sendResponse(false, [], 'Une section doit être spécifiée pour la mise à jour.', 422);
            }

            // Vérifier que la matière est assignée à la section (seulement si subject_id est fourni et pas "toutes les matières")
            if ($request->subject_id && !$applyToAllSubjects) {
                $sectionSubject = SectionSubject::where('section_id', $targetSectionId)
                    ->where('subject_id', $request->subject_id)
                    ->where('school_year_id', $assignment->school_year_id)
                    ->first();

                if (!$sectionSubject) {
                    return ApiResponse::sendResponse(false, [], 'Cette matière n\'est pas assignée à cette section.', 422);
                }
            }

            // Préparer les données de mise à jour
            $updateData = [];
            
            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
            }
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }
            if (isset($data['type'])) {
                $updateData['type'] = $data['type'];
            }
            if (isset($data['max_score'])) {
                $updateData['max_score'] = $data['max_score'];
            }
            if (isset($data['passing_score'])) {
                $updateData['passing_score'] = $data['passing_score'];
            }
            if (isset($data['total_score'])) {
                $updateData['total_score'] = $data['total_score'];
            }
            if (isset($data['start_date'])) {
                $updateData['start_date'] = $data['start_date'];
            }
            if (isset($data['due_date'])) {
                $updateData['due_date'] = $data['due_date'];
            }
            if (isset($data['period'])) {
                $updateData['period'] = $data['period'];
            }
            
            // Mettre à jour la section si fournie
            if ($targetSectionId && $targetSectionId != $assignment->section_id) {
                $updateData['section_id'] = $targetSectionId;
            }
            
            // Mettre à jour la matière
            if ($applyToAllSubjects) {
                $updateData['subject_id'] = null;
            } elseif (isset($data['subject_id'])) {
                $updateData['subject_id'] = $data['subject_id'];
            }
            
            // Mettre à jour l'année scolaire si fournie
            if (isset($data['school_year_id'])) {
                $updateData['school_year_id'] = $data['school_year_id'];
            }

            $assignment->update($updateData);

            DB::commit();
            return ApiResponse::sendResponse(
                true, 
                [new AssignmentResource($assignment->load(['section.classroomTemplate', 'subject', 'schoolYear', 'creator']))], 
                'Devoir mis à jour avec succès.', 
                200
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function destroy(Assignment $assignment)
    {
        // Admin et directeur peuvent supprimer
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à supprimer des devoirs.', 403);
        }

        DB::beginTransaction();
        try {
            $assignment->delete();
            DB::commit();
            return ApiResponse::sendResponse(true, [], 'Devoir supprimé avec succès.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }
}
