<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\CreateCommentRequest;
use App\Http\Requests\Comments\GetCommentsRequest;
use App\Http\Requests\Comments\ReportCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentCollection;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    private CommentRepositoryInterface $commentRepo;

    public function __construct(CommentRepositoryInterface $commentRepo)
    {
        $this->commentRepo = $commentRepo;
    }

    /**
     * Get paginated comments for a post (supports both pagination and load more)
     * 
     * @param GetCommentsRequest $request
     * @param Post $post
     * @return JsonResponse
     */
    public function index(GetCommentsRequest $request, Post $post): JsonResponse
    {
        try {
            $validated = $request->validated();
            $loadMore = $request->boolean('load_more', false);

            if ($loadMore) {
                // Load more mode - cursor-based loading
                $comments = $this->commentRepo->getCommentsForPostLoadMore(
                    postId: $post->id,
                    limit: $validated['per_page'],
                    lastCommentId: $request->get('last_comment_id'),
                    sortBy: $validated['sort_by'],
                    sortOrder: $validated['sort_order']
                );

                return response()->json([
                    'status' => 'success',
                    'message' => 'Comments loaded successfully',
                    'data' => CommentResource::collection($comments['data']),
                    'meta' => [
                        'post' => [
                            'id' => $post->id,
                            'title' => $post->title,
                        ],
                        'stats' => $this->commentRepo->getPostCommentStats($post->id),
                        'load_more' => [
                            'has_more' => $comments['has_more'],
                            'next_cursor' => $comments['next_cursor'],
                            'loaded_count' => $comments['data']->count(),
                        ]
                    ],
                    'timestamp' => now()->toISOString(),
                ], 200);
            } else {
                // Traditional pagination mode
                $comments = $this->commentRepo->getCommentsForPost(
                    postId: $post->id,
                    perPage: $validated['per_page'],
                    sortBy: $validated['sort_by'],
                    sortOrder: $validated['sort_order']
                );

                return (new CommentCollection($comments))
                    ->additional([
                        'meta' => [
                            'post' => [
                                'id' => $post->id,
                                'title' => $post->title,
                            ],
                            'stats' => $this->commentRepo->getPostCommentStats($post->id),
                        ]
                    ])
                    ->response()
                    ->setStatusCode(200);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch comments', [
                'post_id' => $post->id,
                'load_more' => $loadMore ?? false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 400);
        }
    }

    /**
     * Load more comments (dedicated endpoint for load more functionality)
     * 
     * @param Request $request
     * @param Post $post
     * @return JsonResponse
     */
    public function loadMore(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
            'last_comment_id' => 'sometimes|integer|exists:comments,id',
            'sort_by' => 'sometimes|string|in:created_at,likes_count,replies_count',
            'sort_order' => 'sometimes|string|in:asc,desc'
        ]);

        try {
            $comments = $this->commentRepo->getCommentsForPostLoadMore(
                postId: $post->id,
                limit: min((int) $request->get('limit', 15), 50),
                lastCommentId: $request->get('last_comment_id'),
                sortBy: $request->get('sort_by', 'created_at'),
                sortOrder: $request->get('sort_order', 'desc')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'More comments loaded successfully',
                'data' => CommentResource::collection($comments['data']),
                'meta' => [
                    'post' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                    'load_more' => [
                        'has_more' => $comments['has_more'],
                        'next_cursor' => $comments['next_cursor'],
                        'loaded_count' => $comments['data']->count(),
                        'total_loaded' => $request->get('total_loaded', 0) + $comments['data']->count(),
                    ]
                ],
                'timestamp' => now()->toISOString(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to load more comments', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load more comments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 400);
        }
    }

    /**
     * Create a new comment
     * 
     * @param CreateCommentRequest $request
     * @param Post $post
     * @return JsonResponse
     */
    public function store(CreateCommentRequest $request, Post $post): JsonResponse
    {
        try {
            $validated = $request->validated();

            $comment = $this->commentRepo->create([
                'post_id' => $post->id,
                'user_id' => Auth::id(),
                'parent_id' => $validated['parent_id'] ?? null,
                'content' => $validated['content'],
            ]);

            return (new CommentResource($comment))
                ->additional([
                    'status' => 'success',
                    'message' => $comment->is_reply ? 'Reply posted successfully' : 'Comment posted successfully'
                ])
                ->response()
                ->setStatusCode(201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create comment', [
                'post_id' => $post->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create comment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 400);
        }
    }

    /**
     * Get a specific comment
     * 
     * @param Comment $comment
     * @return JsonResponse
     */
    public function show(Comment $comment): JsonResponse
    {
        try {
            $comment = $this->commentRepo->findById($comment->id);

            if (!$comment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Comment not found'
                ], 404);
            }

            return (new CommentResource($comment))
                ->additional([
                    'status' => 'success',
                    'message' => 'Comment retrieved successfully'
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comment', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comment'
            ], 400);
        }
    }

    /**
     * Update a comment
     * 
     * @param UpdateCommentRequest $request
     * @param Comment $comment
     * @return JsonResponse
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        try {
            $validated = $request->validated();

            $updatedComment = $this->commentRepo->update($comment->id, $validated);

            return (new CommentResource($updatedComment))
                ->additional([
                    'status' => 'success',
                    'message' => 'Comment updated successfully'
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update comment', [
                'comment_id' => $comment->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update comment'
            ], 400);
        }
    }

    /**
     * Delete a comment
     * 
     * @param Comment $comment
     * @return JsonResponse
     */
    public function destroy(Comment $comment): JsonResponse
    {
        try {
            $this->commentRepo->delete($comment->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Comment deleted successfully',
                'data' => null
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to delete comment', [
                'comment_id' => $comment->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete comment'
            ], 400);
        }
    }

    /**
     * Toggle like on a comment
     * 
     * @param Comment $comment
     * @return JsonResponse
     */
    public function like(Comment $comment): JsonResponse
    {
        try {
            $result = $comment->toggleLike(Auth::id());

            return response()->json([
                'status' => 'success',
                'message' => ucfirst(str_replace('_', ' ', $result['action'])),
                'data' => [
                    'action' => $result['action'],
                    'likes_count' => $result['likes_count'],
                    'dislikes_count' => $result['dislikes_count'],
                    'user_liked' => $result['user_liked'],
                    'user_disliked' => $result['user_disliked'],
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to toggle comment like', [
                'comment_id' => $comment->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process like action'
            ], 400);
        }
    }

    /**
     * Toggle dislike on a comment
     * 
     * @param Comment $comment
     * @return JsonResponse
     */
    public function dislike(Comment $comment): JsonResponse
    {
        try {
            $result = $comment->toggleDislike(Auth::id());

            return response()->json([
                'status' => 'success',
                'message' => ucfirst(str_replace('_', ' ', $result['action'])),
                'data' => [
                    'action' => $result['action'],
                    'likes_count' => $result['likes_count'],
                    'dislikes_count' => $result['dislikes_count'],
                    'user_liked' => $result['user_liked'],
                    'user_disliked' => $result['user_disliked'],
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to toggle comment dislike', [
                'comment_id' => $comment->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process dislike action'
            ], 400);
        }
    }

    /**
     * Report a comment
     * 
     * @param ReportCommentRequest $request
     * @param Comment $comment
     * @return JsonResponse
     */
    public function report(ReportCommentRequest $request, Comment $comment): JsonResponse
    {
        try {
            $validated = $request->validated();

            $success = $comment->addReport(
                userId: Auth::id(),
                reason: $validated['reason'],
                description: $validated['description'] ?? null
            );

            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already reported this comment'
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Comment reported successfully. Thank you for helping keep our community safe.',
                'data' => [
                    'reports_count' => $comment->fresh()->reports_count ?? $comment->reports()->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to report comment', [
                'comment_id' => $comment->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to report comment'
            ], 400);
        }
    }

    /**
     * Get comments by current user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function myComments(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);

            $comments = $this->commentRepo->getCommentsByUser(Auth::id(), $perPage);

            return (new CommentCollection($comments))
                ->additional([
                    'message' => 'Your comments retrieved successfully'
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user comments', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch your comments'
            ], 400);
        }
    }

    /**
     * Search comments (admin/moderator feature)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:3|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        try {
            $query = $request->get('q');
            $perPage = min((int) $request->get('per_page', 15), 100);

            $comments = $this->commentRepo->search($query, $perPage);

            return (new CommentCollection($comments))
                ->additional([
                    'message' => "Search results for: {$query}",
                    'meta' => [
                        'search_query' => $query
                    ]
                ])
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            Log::error('Failed to search comments', [
                'query' => $request->get('q'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search comments'
            ], 400);
        }
    }
}
