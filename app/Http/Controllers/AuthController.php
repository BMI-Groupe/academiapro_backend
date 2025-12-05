<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistrationRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthInterface;
use App\Models\User;
use App\Models\Teacher;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
