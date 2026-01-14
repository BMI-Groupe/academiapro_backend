<?php

namespace App\Http\Controllers;

use App\Models\PedagogicalResource;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PedagogicalResourceController extends Controller
{
    public function index(Request $request)
    {
        $schoolYearId = $request->query('school_year_id') ?? SchoolYear::active()?->id;
        
        $query = PedagogicalResource::with(['teacher.user', 'section', 'subject'])
            ->where('school_year_id', $schoolYearId);

        if ($request->has('section_id')) {
            $query->where(function($q) use ($request) {
                $q->where('section_id', $request->section_id)
                  ->orWhereNull('section_id');
            });
        }

        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $resources = $query->latest()->get();

        return ApiResponse::sendResponse(true, $resources, 'Ressources récupérées.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:course,assignment,exam,other',
            'file' => 'required|file|max:20480', // 20MB max
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);

        $user = auth()->user();
        $schoolYearId = $request->input('school_year_id') ?? SchoolYear::active()?->id;

        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active trouvée.', 400);
        }

        $schoolId = $user->school_id;
        
        // Si l'utilisateur n'a pas d'école (ex: super admin), on prend l'école de l'année scolaire ou la première école dispo
        if (!$schoolId) {
            $activeYear = SchoolYear::find($schoolYearId);
            $schoolId = $activeYear?->school_id ?? \App\Models\School::first()?->id;
        }

        if (!$schoolId) {
            return ApiResponse::sendResponse(false, [], 'Aucune école associée trouvée.', 400);
        }

        $teacherId = null;
        if ($user->role === 'enseignant') {
            $teacher = Teacher::where('user_id', $user->id)->first();
            $teacherId = $teacher?->id;
        }

        DB::beginTransaction();

        try {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $fileExtension = $file->getClientOriginalExtension();
            $path = $file->store('pedagogical-resources', 'public');

            $resource = PedagogicalResource::create([
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_type' => $fileExtension,
                'type' => $request->type,
                'subject_id' => $request->subject_id,
                'section_id' => $request->section_id,
                'teacher_id' => $teacherId,
                'school_year_id' => $schoolYearId,
                'school_id' => $schoolId,
            ]);

            DB::commit();

            return ApiResponse::sendResponse(true, $resource, 'Ressource ajoutée avec succès.', 201);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function show(PedagogicalResource $resource)
    {
        $resource->load(['teacher.user', 'section', 'subject']);
        return ApiResponse::sendResponse(true, $resource, 'Ressource récupérée.');
    }

    public function download(PedagogicalResource $resource)
    {
        if (!Storage::disk('public')->exists($resource->file_path)) {
            return ApiResponse::sendResponse(false, [], 'Fichier introuvable.', 404);
        }

        $path = Storage::disk('public')->path($resource->file_path);
        
        return response()->download($path, $resource->file_name);
    }

    public function destroy(PedagogicalResource $resource)
    {
        $user = auth()->user();
        
        // Only owner (teacher) or admin/director can delete
        if ($user->role === 'enseignant') {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if (!$teacher || $resource->teacher_id !== $teacher->id) {
                return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à supprimer cette ressource.', 403);
            }
        } elseif ($user->role !== 'admin' && $user->role !== 'directeur') {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à supprimer cette ressource.', 403);
        }

        try {
            if (Storage::disk('public')->exists($resource->file_path)) {
                Storage::disk('public')->delete($resource->file_path);
            }
            $resource->delete();

            return ApiResponse::sendResponse(true, [], 'Ressource supprimée avec succès.');
        } catch (\Throwable $th) {
            return ApiResponse::throw($th);
        }
    }
}
