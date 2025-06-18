<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthRepository implements AuthRepositoryInterface
{
    public function register(array $data): mixed
    {
        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            if (!$user) {
                throw new \Exception('User registration failed');
            }

            // Create token for the newly registered user
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function login(array $credentials): mixed
    {
        try {
            // Find user by email
            $user = User::where('email', $credentials['email'])->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                throw new \Exception('Invalid credentials');
            }

            // Optional: Delete old tokens if you want single device login
            // $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function logout($user): bool
    {
        try {
            // Delete current token
            $user->currentAccessToken()->delete();
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function logoutAll($user): bool
    {
        try {
            // Delete all tokens for the user
            $user->tokens()->delete();
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
