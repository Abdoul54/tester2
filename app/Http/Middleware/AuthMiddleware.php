<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();
            $user = Auth::check();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: Invalid token',
                ], 401);
            }
            // Check if the token is provided
            if (!$token && !$request->hasHeader('Authorization')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: No token provided',
                ], 401);
            }
            // If the token is provided, check if it exists
            if ($request->hasHeader('Authorization')) {
                $token = $request->header('Authorization');
            }
            // If the token is still not found, return an error
            if (is_null($token) || empty($token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: No token provided',
                ], 401);
            }
            // If the token is not valid, return an error
            if (!preg_match('/^Bearer\s(\S+)$/', $token, $matches)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: Invalid token format',
                ], 401);
            }
            $token = $matches[1];
            // If the token is valid, proceed with the request
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: No token provided',
                ], 401);
            }

            return $next($request);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
