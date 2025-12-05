<?php

namespace App\Http\Controllers;

use App\Models\EvaluationType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EvaluationTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EvaluationType::query();

        if ($request->has('school_year_id')) {
            $query->where('school_year_id', $request->school_year_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0',
            'school_year_id' => 'required|exists:school_years,id',
        ]);

        $evaluationType = EvaluationType::create($validated);

        return response()->json([
            'success' => true,
            'data' => $evaluationType,
            'message' => 'Type d\'évaluation créé avec succès.'
        ], 201);
    }

    public function update(Request $request, EvaluationType $evaluationType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'weight' => 'sometimes|numeric|min:0',
            'school_year_id' => 'sometimes|exists:school_years,id',
        ]);

        $evaluationType->update($validated);

        return response()->json([
            'success' => true,
            'data' => $evaluationType,
            'message' => 'Type d\'évaluation mis à jour avec succès.'
        ]);
    }

    public function destroy(EvaluationType $evaluationType): JsonResponse
    {
        $evaluationType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type d\'évaluation supprimé avec succès.'
        ]);
    }
}
