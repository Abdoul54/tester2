<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
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

        // Comments routes
        Route::get('posts/{post}/comments', [CommentController::class, 'index'])->name('comments.index');
        Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');

        // Load more comments endpoint
        Route::get('posts/{post}/comments/load-more', [CommentController::class, 'loadMore'])->name('comments.load-more');

        Route::prefix('comments/{comment}')->group(function () {
            // Update comment (only by author, within time limit)
            Route::put('/', [CommentController::class, 'update'])
                ->name('comments.update');

            // Delete comment (only by author)
            Route::delete('/', [CommentController::class, 'destroy'])
                ->name('comments.destroy');

            // Interaction routes
            Route::post('/like', [CommentController::class, 'like'])
                ->name('comments.like');

            Route::post('/dislike', [CommentController::class, 'dislike'])
                ->name('comments.dislike');

            Route::post('/report', [CommentController::class, 'report'])
                ->name('comments.report');
        });

        // User-specific routes
        Route::get('/user/comments', [CommentController::class, 'myComments'])
            ->name('comments.my');

        // Search comments (for moderation or user search)
        Route::get('/comments/search', [CommentController::class, 'search'])
            ->name('comments.search');
    });
});
