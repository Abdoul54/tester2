<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Repositories\Contracts\AuthRepositoryInterface;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private AuthRepositoryInterface $authRepo;

    public function __construct(AuthRepositoryInterface $authRepo)
    {
        $this->authRepo = $authRepo;
    }

    /**
     * Handle user registration.
     */
    public function register(RegisterRequest $request)
    {
        try {
            $user = $this->authRepo->register($request->validated());

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User registration failed',
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'User registered successfully',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle user login.
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();

            $user = $this->authRepo->login($credentials);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials',
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'User logged in successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle user logout (current device).
     */
    public function logout(Request $request)
    {
        try {
            $result = $this->authRepo->logout($request->user());

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Logout failed',
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle user logout from all devices.
     */
    public function logoutAll(Request $request)
    {
        try {
            $result = $this->authRepo->logoutAll($request->user());

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Logout from all devices failed',
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logged out from all devices successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 400);
        }
    }
}
