<?php

namespace App\Interfaces;

interface AuthInterface
{
    //
    public function register(array $data);
    public function login(array $data);
    public function checkOtpCode(array $data);
    public function logout();
    public function getUser();
    public function updateUser(array $data);
    public function deleteUser();
    public function changePassword(array $data);
    public function forgotPassword(array $data);
    public function resetPassword(array $data);
    
}
