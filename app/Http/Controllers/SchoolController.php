<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use App\Responses\ApiResponse;
use Illuminate\Support\Facades\Storage;

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schools = School::orderBy('created_at', 'desc')->get();
        return ApiResponse::sendResponse(true, $schools, 'Liste des écoles récupérée.', 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone', // Phone requis et unique pour le directeur
            'email' => 'required|email|max:255|unique:users,email', // Email requis pour l'envoi
            'logo' => 'nullable|image|max:2048', // 2MB Max
        ]);

        \DB::beginTransaction();
        
        try {
            // Upload du logo si présent
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('schools/logos', 'public');
                $validated['logo'] = $path;
            }

            // Créer l'école
            $school = School::create($validated);

            // Générer un mot de passe aléatoire (8 caractères alphanumériques)
            $generatedPassword = \Str::random(8);

            // Créer le compte directeur
            $director = \App\Models\User::create([
                'name' => 'Directeur ' . $school->name,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => \Hash::make($generatedPassword),
                'role' => 'directeur',
                'school_id' => $school->id,
            ]);

            \DB::commit();

            // Retourner les données avec le mot de passe en clair pour l'envoi d'email
            return ApiResponse::sendResponse(true, [
                'school' => $school,
                'director' => [
                    'id' => $director->id,
                    'name' => $director->name,
                    'email' => $director->email,
                    'phone' => $director->phone,
                    'password' => $generatedPassword, // Mot de passe en clair pour l'email
                ]
            ], 'École créée avec succès. Un compte directeur a été créé.', 201);
            
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            \DB::rollback();
            // Gérer les erreurs de contrainte unique
            if (str_contains($e->getMessage(), 'users_email_unique')) {
                return ApiResponse::sendResponse(
                    false, 
                    [], 
                    'Cet email est déjà utilisé par un autre utilisateur. Veuillez utiliser un autre email.', 
                    422
                );
            } elseif (str_contains($e->getMessage(), 'users_phone_unique')) {
                return ApiResponse::sendResponse(
                    false, 
                    [], 
                    'Ce numéro de téléphone est déjà utilisé par un autre utilisateur. Veuillez utiliser un autre numéro.', 
                    422
                );
            }
            
            return ApiResponse::sendResponse(
                false, 
                [], 
                'Une erreur de duplication est survenue : ' . $e->getMessage(), 
                422
            );
        } catch (\Exception $e) {
            \DB::rollback();
            return ApiResponse::sendResponse(false, [], 'Erreur lors de la création de l\'école: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(School $school)
    {
        return ApiResponse::sendResponse(true, [$school], 'Détails de l\'école récupérés.', 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, School $school)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|max:2048',
            'is_active' => 'boolean'
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $path = $request->file('logo')->store('schools/logos', 'public');
            $validated['logo'] = $path;
        }

        $school->update($validated);

        return ApiResponse::sendResponse(true, [$school], 'École mise à jour avec succès.', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(School $school)
    {
        if ($school->logo) {
            Storage::disk('public')->delete($school->logo);
        }
        $school->delete();

        return ApiResponse::sendResponse(true, [], 'École supprimée avec succès.', 200);
    }
}
