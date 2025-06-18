<?php

namespace App\Repositories;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\StorageRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class PostRepository implements PostRepositoryInterface
{
    private StorageRepositoryInterface $storageRepo;

    public function __construct(StorageRepositoryInterface $storageRepo)
    {
        $this->storageRepo = $storageRepo;
    }

    public function createPost(array $data): mixed
    {
        try {
            $thumbnailPath = $this->storageRepo->storeFile($data['thumbnail']) ?? null;

            dd($thumbnailPath);
            $post = Post::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'user_id' => Auth::id(),
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'tags' => $data['tags'] ?? [],
                'thumbnail' => $thumbnailPath
            ]);



            // Load the user relationship for the resource
            $post->load('user');

            return $post;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getPost(int $id): mixed
    {
        try {
            return Post::with('user')->findOrFail($id);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getAllPosts(array $params): mixed
    {
        try {
            $pageSize = $params['page_size'] ?? 10;
            $page = $params['page'] ?? 1;
            $sortBy = $params['sort_by'] ?? 'created_at';
            $sortOrder = $params['sort_order'] ?? 'desc';
            $search = $params['search'] ?? null;

            $query = Post::with('user');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereJsonContains('tags', $search);
                });
            }

            $posts = $query->orderBy($sortBy, $sortOrder)
                ->paginate($pageSize, ['*'], 'page', $page);

            return [
                'items' => $posts->items(),
                'pagination' => [
                    'total' => $posts->total(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                ],
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getPostsByUserId(array $params): mixed
    {
        try {
            $pageSize = $params['page_size'] ?? 10;
            $page = $params['page'] ?? 1;
            $sortBy = $params['sort_by'] ?? 'created_at';
            $sortOrder = $params['sort_order'] ?? 'desc';
            $search = $params['search'] ?? null;



            $query = Post::with('user');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereJsonContains('tags', $search);
                });
            }

            $posts = $query->where('user_id', Auth::id())
                ->orderBy($sortBy, $sortOrder)
                ->paginate($pageSize, ['*'], 'page', $page);

            return [
                'items' => $posts->items(),
                'pagination' => [
                    'total' => $posts->total(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                ],
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function updatePost(int $id, array $data): mixed
    {
        try {
            $post = Post::findOrFail($id);
            $userId = Auth::id();

            if ($post->user_id !== $userId) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have permission to update this post.'
                );
            }

            $post->update($data);

            // Load the user relationship for the resource
            $post->load('user');

            return $post;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function deletePost(int $id): mixed
    {
        try {
            $post = Post::findOrFail($id);
            $userId = Auth::id();

            if ($post->user_id !== $userId) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have permission to delete this post.'
                );
            }

            $post->delete();

            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
