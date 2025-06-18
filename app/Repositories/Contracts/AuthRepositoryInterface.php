<?php

namespace App\Repositories\Contracts;

interface AuthRepositoryInterface
{
    public function register(array $data): mixed;

    public function login(array $credentials): mixed;

    public function logout($user): bool;

    public function logoutAll($user): bool;
}
