<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistrationRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthInterface;
use App\Models\User;
use App\Models\Teacher;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
    private AuthInterface $authInterface;

    public function __construct(AuthInterface $authInterface)
    {
        $this->authInterface = $authInterface;
    }

    public function register(RegistrationRequest $registerRequest)
    {
        if (! auth()->check() || auth()->user()->role !== 'directeur') {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $data = [
            'name' => $registerRequest->name,
            'email' => $registerRequest->email,
            'password' => $registerRequest->password,
            'role' => $registerRequest->role,
        ];

        DB::beginTransaction();

        try {
            $user = $this->authInterface->register($data);

            if ($registerRequest->role === 'enseignant') {
                Teacher::create([
                    'user_id' => $user->id,
                    'first_name' => $registerRequest->first_name,
                    'last_name' => $registerRequest->last_name,
                    'phone' => $registerRequest->phone,
                    'specialization' => $registerRequest->profession,
                ]);
            }

            // $this->otpCodeInterface->deleteByEmail($data['email']);
            // $this->otpCodeInterface->store($otpCode);

            // Mail::to($data['email'])->send(new OTPCodeEmail($user->fullName, $code));

            DB::commit();

            return ApiResponse::sendResponse(true, [new UserResource($user)], 'Opération effectuée.', 201);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function login(LoginRequest $loginRequest)
    {
        $data = [
            'phone' => $loginRequest->phone,
            'password' => $loginRequest->password,
        ];

        DB::beginTransaction();

        try {
            $result = $this->authInterface->login($data);
            DB::commit();

            if (!$result) {
                return ApiResponse::sendResponse(
                    false,
                    [],
                    'Nom d\'utilisateur ou mot de passe incorrecte !',
                    401
                );
            }

            return ApiResponse::sendResponse(
                true,
                [$result],
                'Connexion effectuée.',
                200
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function checkOtpCode(Request $request)
    {
        $data = [
            'email' => $request->email,
            'code' => $request->code,
        ];

        DB::beginTransaction();

        try {
            $user = $this->authInterface->checkOtpCode($data);
            DB::commit();

            if (!$user) {
                return ApiResponse::sendResponse(
                    false,
                    [],
                    'Code de confirmation invalide!',
                    200
                );
            }

            return ApiResponse::sendResponse(
                true,
                [new UserResource($user)],
                'Opération éffectuée.',
                200
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function logout()
    {

        $user = User::find(auth()->user()->getAuthIdentifier());
        $user->tokens()->delete();

        return ApiResponse::sendResponse(
            true,
            [],
            'Utilisateur déconnecté',
            200
        );
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return ApiResponse::sendResponse(
            true,
            [new UserResource($user)],
            'Utilisateur récupéré.',
            200
        );
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();
        
        DB::beginTransaction();

        try {
            // Mise à jour des informations de base (SANS le téléphone)
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }

            // Gestion de la photo de profil
            if ($request->hasFile('photo')) {
                // Supprimer l'ancienne photo si elle existe et n'est pas celle par défaut
                if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
                    Storage::disk('public')->delete($user->profile_photo_path);
                }
                
                $path = $request->file('photo')->store('profile-photos', 'public');
                $user->profile_photo_path = $path;
            }

            // Gestion du mot de passe
            if (isset($data['new_password'])) {
                $user->password = Hash::make($data['new_password']);
            }

            $user->save();
            
            // Si c'est un enseignant, mettre à jour l'email dans teacher aussi
            if ($user->role === 'enseignant') {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if ($teacher && isset($data['email'])) {
                    $teacher->email = $data['email'];
                    $teacher->save();
                }
            }

            DB::commit();

            return ApiResponse::sendResponse(
                true,
                [new UserResource($user)],
                'Profil mis à jour avec succès.',
                200
            );

        } catch (\Throwable $th) {
            DB::rollback();
            return ApiResponse::rollback($th);
        }
    }

    /**
     * Génère un OTP pour le reset mot de passe et le renvoie pour EmailJS
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $request->email)->first();
        if (!$user) {
             return ApiResponse::sendResponse(false, [], 'Aucun compte associé à cet email.', 404);
        }

        // Générer OTP (6 digits)
        $otp = (string) rand(100000, 999999);
        
        // Stocker OTP hashé
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now()
            ]
        );

        // On retourne l'OTP pour l'envoi par EmailJS côté front
        return ApiResponse::sendResponse(true, [
            'email' => $user->email, 
            'otp' => $otp, 
            'name' => $user->name
        ], 'Code de réinitialisation généré.', 200);
    }

    /**
     * Réinitialise le mot de passe avec le code OTP
     */
    public function resetPassword(Request $request) 
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:8'
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        
        if (!$record || !Hash::check($request->otp, $record->token)) {
             return ApiResponse::sendResponse(false, [], 'Code invalide.', 400);
        }

        // Expiration de 15 min (token) ou plus large, disons 60 min ici
        if (\Carbon\Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
             return ApiResponse::sendResponse(false, [], 'Code expiré.', 400);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();
            
            // Nettoyage
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return ApiResponse::sendResponse(true, [], 'Mot de passe réinitialisé avec succès.', 200);
        }
        
        return ApiResponse::sendResponse(false, [], 'Utilisateur introuvable.', 404);
    }
}
