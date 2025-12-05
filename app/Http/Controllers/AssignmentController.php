<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignmentStoreRequest;
use App\Http\Requests\AssignmentUpdateRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\SchoolYear;
use App\Models\ClassroomSubject;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Assignment::with(['classroom', 'subject', 'schoolYear', 'creator']);

        // Filtres optionnels
        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
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
            // Récupérer l'année scolaire active
            $activeYear = SchoolYear::where('is_active', true)->first();

            if (!$activeYear) {
                return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active trouvée.', 422);
            }

            // Vérifier que la matière est assignée à la classe (seulement si subject_id est fourni)
            if ($request->subject_id) {
                $classroomSubject = ClassroomSubject::where('classroom_id', $request->classroom_id)
                    ->where('subject_id', $request->subject_id)
                    ->first();

                if (!$classroomSubject) {
                    return ApiResponse::sendResponse(false, [], 'Cette matière n\'est pas assignée à cette classe.', 422);
                }
            }

            $data = $request->validated();
            $data['school_year_id'] = $activeYear->id;
            $data['created_by'] = Auth::id();

            $assignment = Assignment::create($data);

            DB::commit();
            return ApiResponse::sendResponse(true, [new AssignmentResource($assignment)], 'Devoir créé avec succès.', 201);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function show(Assignment $assignment)
    {
        return ApiResponse::sendResponse(
            true,
            [new AssignmentResource($assignment->load(['classroom', 'subject', 'schoolYear', 'creator']))],
            'Opération effectuée.',
            200
        );
    }

    public function update(AssignmentUpdateRequest $request, Assignment $assignment)
    {
        DB::beginTransaction();
        try {
            // Si la classe ou la matière change, vérifier l'assignation (seulement si subject_id est fourni)
            if ($request->has('classroom_id') && $request->has('subject_id') && $request->subject_id) {
                $classroomSubject = ClassroomSubject::where('classroom_id', $request->classroom_id)
                    ->where('subject_id', $request->subject_id)
                    ->first();

                if (!$classroomSubject) {
                    return ApiResponse::sendResponse(false, [], 'Cette matière n\'est pas assignée à cette classe.', 422);
                }
            }

            $assignment->update($request->validated());

            DB::commit();
            return ApiResponse::sendResponse(true, [new AssignmentResource($assignment)], 'Devoir mis à jour avec succès.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function destroy(Assignment $assignment)
    {
        // Seul le directeur peut supprimer
        if (Auth::user()->role !== 'directeur') {
            return ApiResponse::sendResponse(false, [], 'Seul le directeur peut supprimer des devoirs.', 403);
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
