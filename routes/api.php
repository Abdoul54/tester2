<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;

// Force JSON responses for all API routes
Route::middleware(['api'])->group(function () {


    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::get('/user/posts', [PostController::class, 'getPostsByUserId'])->name('user.posts');
    });
});
