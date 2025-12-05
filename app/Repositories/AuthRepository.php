<?php

namespace App\Repositories;

use App\Interfaces\AuthInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class AuthRepository implements AuthInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function register(array $data)
    {
        return User::create($data);
    }

    public function login(array $data)
    {
        // login by phone instead of email
        $user = User::where('phone', $data['phone'])->first();

        if (!$user)
            return false;

        if (!Hash::check($data['password'], $user->password)) {
            return false;
        }

        // Supprimer les anciens tokens
        $user->tokens()->delete();
        
        // Créer un nouveau token
        $token = $user->createToken($user->id)->plainTextToken;
        
        // Retourner les données dans le format attendu
        return [
            'user' => $user,
            'tokens' => [
                'accessToken' => $token,
                'refreshToken' => $token,
            ]
        ];
    }

    public function checkOtpCode(array $data)
    {
        return false;
    }

    public function logout()
    {
        return true;
    }

    public function getUser()
    {
        return auth('sanctum')->user();
    }

    public function updateUser(array $data)
    {
        $user = auth('sanctum')->user();
        $user->update($data);
        return $user;
    }

    public function deleteUser()
    {
        $user = auth('sanctum')->user();
        $user->tokens()->delete();
        $user->delete();
        return true;
    }

    public function changePassword(array $data)
    {
        $user = auth('sanctum')->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return false;
        }
        $user->update(['password' => Hash::make($data['new_password'])]);
        return true;
    }

    public function forgotPassword(array $data)
    {
        return true;
    }

    public function resetPassword(array $data)
    {
        return true;
    }
}

