<?php

namespace App\Http\Controllers;

use App\Http\Requests\Posts\CreatePostRequest;
use App\Http\Requests\Posts\DeletePostRequest;
use App\Http\Requests\Posts\GetAllPostsRequest;
use App\Http\Requests\Posts\GetPostsByUserIdRequest;
use App\Http\Requests\Posts\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostCollection;
use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    private PostRepositoryInterface $postRepo;

    public function __construct(PostRepositoryInterface $postRepo)
    {
        $this->postRepo = $postRepo;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetAllPostsRequest $request): JsonResponse
    {
        try {
            $posts = $this->postRepo->getAllPosts($request->validated());

            // Create a paginated collection from the repository response
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $posts['items'],
                $posts['pagination']['total'],
                $posts['pagination']['per_page'],
                $posts['pagination']['current_page'],
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );

            return (new PostCollection($paginator))
                ->response()
                ->setStatusCode(200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePostRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $post = $this->postRepo->createPost($data);

            return (new PostResource($post))
                ->additional([
                    'status' => 'success',
                    'message' => 'Post created successfully'
                ])
                ->response()
                ->setStatusCode(201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): JsonResponse
    {
        try {
            $post = $this->postRepo->getPost($post->id);

            return (new PostResource($post))
                ->additional([
                    'status' => 'success',
                    'message' => 'Post retrieved successfully'
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $data = $request->validated();

        try {
            $post = $this->postRepo->updatePost($post->id, $data);

            return (new PostResource($post))
                ->additional([
                    'status' => 'success',
                    'message' => 'Post updated successfully'
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post): JsonResponse
    {
        try {
            $this->postRepo->deletePost($post->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Post deleted successfully',
                'data' => null
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Get posts by user ID.
     */
    public function getPostsByUserId(GetPostsByUserIdRequest $request): JsonResponse
    {
        try {
            $posts = $this->postRepo->getPostsByUserId($request->validated());

            // Create a paginated collection from the repository response
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $posts['items'],
                $posts['pagination']['total'],
                $posts['pagination']['per_page'],
                $posts['pagination']['current_page'],
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );

            return (new PostCollection($paginator))
                ->additional([
                    'message' => 'User posts retrieved successfully'
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
