<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs avec scope de sécurité (Admin vs Directeur)
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $query = User::query()->with('school');

        // 1. Filtrage de sécurité (Scope)
        if ($currentUser->role !== 'admin') {
            // Un directeur ne voit que les utilisateurs de son école
            if ($currentUser->school_id) {
                $query->where('school_id', $currentUser->school_id);
            } else {
                // Par sécurité, si pas admin et pas d'école, on retourne vide
                return ApiResponse::sendResponse(true, ['data' => [], 'meta' => []], 'Aucun utilisateur visible.', 200);
            }
        }

        // 2. Filtres de recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 3. Filtre par rôle
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // 4. Pagination
        $perPage = $request->input('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::sendResponse(
            true, 
            [
                'data' => UserResource::collection($users->items()),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
            ], 
            'Liste des utilisateurs récupérée.', 
            200
        );
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        
        // Sécurité : Seuls admin et directeur peuvent créer
        if ($currentUser->role !== 'admin' && $currentUser->role !== 'directeur') {
             return ApiResponse::sendResponse(false, [], 'Non autorisé.', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|in:admin,directeur,secretaire,comptable', // Rôles système uniquement
            'school_id' => 'nullable|exists:schools,id',
        ]);

        // Logique métier pour school_id et permissions de rôle
        if ($currentUser->role === 'directeur') {
            // Le directeur ne peut créer que pour son école
            $validated['school_id'] = $currentUser->school_id;
            
            // Le directeur ne peut pas créer d'admin
            if ($validated['role'] === 'admin') {
                 return ApiResponse::sendResponse(false, [], 'Un directeur ne peut pas créer un administrateur.', 403);
            }
        }

        // Génération automatique du mot de passe
        $plainPassword = Str::random(10);
        $validated['password'] = Hash::make($plainPassword);

        $user = User::create($validated);

        return ApiResponse::sendResponse(true, [
            'user' => new UserResource($user),
            'credentials' => [
                'email' => $user->email,
                'password' => $plainPassword,
                'phone' => $user->phone
            ]
        ], 'Utilisateur créé avec succès.', 201);
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy(User $user)
    {
        $currentUser = Auth::user();

        // Sécurité : Vérifier les droits
        if ($currentUser->role !== 'admin' && $currentUser->role !== 'directeur') {
             return ApiResponse::sendResponse(false, [], 'Non autorisé.', 403);
        }

        // Empêcher de supprimer son propre compte
        if ($user->id === $currentUser->id) {
            return ApiResponse::sendResponse(false, [], 'Vous ne pouvez pas supprimer votre propre compte.', 400);
        }

        // Si administrateur, tout pouvoir (sauf restrictions métier éventuelles)
        
        // Si directeur, vérifier que l'utilisateur cible est dans son école
        if ($currentUser->role === 'directeur') {
            if ($user->school_id !== $currentUser->school_id) {
                return ApiResponse::sendResponse(false, [], 'Vous ne pouvez pas supprimer un utilisateur d\'une autre école.', 403);
            }
        }
        
        // Soft delete ou Hard delete? User utilise SoftDeletes s'il a le trait, sinon delete normal.
        // Ici on suppose delete normal pour l'instant sauf si SoftDeletes est configuré.
        //$user->tokens()->delete(); // Déconnecter l'user
        $user->delete();

        return ApiResponse::sendResponse(true, [], 'Utilisateur supprimé.', 200);
    }
}
