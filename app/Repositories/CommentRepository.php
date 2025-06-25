<?php
// app/Repositories/CommentRepository.php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Post;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommentRepository implements CommentRepositoryInterface
{
    /**
     * Get paginated comments for a post with nested replies
     */
    public function getCommentsForPost(int $postId, int $perPage = 15, string $sortBy = 'created_at', string $sortOrder = 'desc'): LengthAwarePaginator
    {
        return Comment::with(['user:id,name,email'])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->where('post_id', $postId)
            ->whereNull('parent_id') // Only get top-level comments for now
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Get comments for a post using load more functionality (cursor-based)
     */
    public function getCommentsForPostLoadMore(int $postId, int $limit = 15, ?int $lastCommentId = null, string $sortBy = 'created_at', string $sortOrder = 'desc'): array
    {
        $query = Comment::with(['user:id,name,email'])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->where('post_id', $postId)
            ->whereNull('parent_id'); // Only get top-level comments

        // Apply cursor-based pagination
        if ($lastCommentId) {
            $lastComment = Comment::find($lastCommentId);
            if ($lastComment) {
                if ($sortOrder === 'desc') {
                    if ($sortBy === 'created_at') {
                        $query->where('created_at', '<', $lastComment->created_at)
                            ->orWhere(function ($q) use ($lastComment) {
                                $q->where('created_at', '=', $lastComment->created_at)
                                    ->where('id', '<', $lastComment->id);
                            });
                    } elseif ($sortBy === 'likes_count') {
                        $query->whereRaw('(SELECT COUNT(*) FROM comment_likes WHERE comment_id = comments.id) < ?', [$lastComment->likes_count])
                            ->orWhere(function ($q) use ($lastComment) {
                                $q->whereRaw('(SELECT COUNT(*) FROM comment_likes WHERE comment_id = comments.id) = ?', [$lastComment->likes_count])
                                    ->where('id', '<', $lastComment->id);
                            });
                    } elseif ($sortBy === 'replies_count') {
                        $query->whereRaw('(SELECT COUNT(*) FROM comments c WHERE c.parent_id = comments.id) < ?', [$lastComment->replies_count])
                            ->orWhere(function ($q) use ($lastComment) {
                                $q->whereRaw('(SELECT COUNT(*) FROM comments c WHERE c.parent_id = comments.id) = ?', [$lastComment->replies_count])
                                    ->where('id', '<', $lastComment->id);
                            });
                    }
                } else { // asc
                    if ($sortBy === 'created_at') {
                        $query->where('created_at', '>', $lastComment->created_at)
                            ->orWhere(function ($q) use ($lastComment) {
                                $q->where('created_at', '=', $lastComment->created_at)
                                    ->where('id', '>', $lastComment->id);
                            });
                    } elseif ($sortBy === 'likes_count') {
                        $query->whereRaw('(SELECT COUNT(*) FROM comment_likes WHERE comment_id = comments.id) > ?', [$lastComment->likes_count])
                            ->orWhere(function ($q) use ($lastComment) {
                                $q->whereRaw('(SELECT COUNT(*) FROM comment_likes WHERE comment_id = comments.id) = ?', [$lastComment->likes_count])
                                    ->where('id', '>', $lastComment->id);
                            });
                    } elseif ($sortBy === 'replies_count') {
                        $query->whereRaw('(SELECT COUNT(*) FROM comments c WHERE c.parent_id = comments.id) > ?', [$lastComment->replies_count])
                            ->orWhere(function ($q) use ($lastComment) {
                                $q->whereRaw('(SELECT COUNT(*) FROM comments c WHERE c.parent_id = comments.id) = ?', [$lastComment->replies_count])
                                    ->where('id', '>', $lastComment->id);
                            });
                    }
                }
            }
        }

        // Order by the specified field and then by ID for consistent pagination
        $query->orderBy($sortBy, $sortOrder)
            ->orderBy('id', $sortOrder);

        // Get one extra record to check if there are more results
        $comments = $query->limit($limit + 1)->get();

        $hasMore = $comments->count() > $limit;
        if ($hasMore) {
            $comments = $comments->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $comments->isNotEmpty()) {
            $nextCursor = $comments->last()->id;
        }

        return [
            'data' => $comments,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * Get all comments for a post without pagination (for exports, etc.)
     */
    public function getAllCommentsForPost(int $postId): Collection
    {
        return Comment::with([
            'user:id,name,email',
            'replies.user:id,name,email'
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->forPost($postId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new comment
     */
    public function create(array $data): Comment
    {
        DB::beginTransaction();

        try {
            // Validate that the post exists
            if (!Post::find($data['post_id'])) {
                throw new \InvalidArgumentException('Post not found');
            }

            // If it's a reply, validate that the parent comment exists and belongs to the same post
            if (!empty($data['parent_id'])) {
                $parentComment = Comment::find($data['parent_id']);
                if (!$parentComment || $parentComment->post_id !== $data['post_id']) {
                    throw new \InvalidArgumentException('Invalid parent comment');
                }
            }

            $comment = Comment::create([
                'post_id' => $data['post_id'],
                'user_id' => $data['user_id'] ?? Auth::id(),
                'parent_id' => $data['parent_id'] ?? null,
                'content' => $data['content'],
            ]);

            // Load relationships for the response
            $comment->load(['user:id,name,email'])
                ->loadCount(['likes', 'dislikes', 'reports', 'replies']);

            DB::commit();

            return $comment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Find a comment by ID with all relationships
     */
    public function findById(int $id): ?Comment
    {
        return Comment::with([
            'user:id,name,email',
            'post:id,title',
            'parent.user:id,name,email',
            'replies.user:id,name,email'
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->find($id);
    }

    /**
     * Update a comment
     */
    public function update(int $id, array $data): Comment
    {
        $comment = $this->findById($id);

        if (!$comment) {
            throw new ModelNotFoundException('Comment not found');
        }

        if (!$comment->canBeEditedBy()) {
            throw new \InvalidArgumentException('Comment cannot be edited');
        }

        DB::beginTransaction();

        try {
            $oldContent = $comment->content;

            $comment->update([
                'content' => $data['content']
            ]);

            // Mark as edited if content changed
            if ($oldContent !== $data['content']) {
                $comment->markAsEdited();
            }

            DB::commit();

            return $comment->fresh([
                'user:id,name,email'
            ])->loadCount(['likes', 'dislikes', 'reports', 'replies']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a comment (soft delete)
     */
    public function delete(int $id): bool
    {
        $comment = $this->findById($id);

        if (!$comment) {
            throw new ModelNotFoundException('Comment not found');
        }

        if (!$comment->canBeDeletedBy()) {
            throw new \InvalidArgumentException('Comment cannot be deleted');
        }

        return $comment->delete();
    }

    /**
     * Restore a soft-deleted comment
     */
    public function restore(int $id): bool
    {
        $comment = Comment::withTrashed()->find($id);

        if (!$comment) {
            throw new ModelNotFoundException('Comment not found');
        }

        return $comment->restore();
    }

    /**
     * Permanently delete a comment
     */
    public function forceDelete(int $id): bool
    {
        $comment = Comment::withTrashed()->find($id);

        if (!$comment) {
            throw new ModelNotFoundException('Comment not found');
        }

        return $comment->forceDelete();
    }

    /**
     * Get comments by user with load more support
     */
    public function getCommentsByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Comment::with([
            'user:id,name,email',
            'post:id,title'
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get comments by user with load more functionality
     */
    public function getCommentsByUserLoadMore(int $userId, int $limit = 15, ?int $lastCommentId = null, string $sortBy = 'created_at', string $sortOrder = 'desc'): array
    {
        $query = Comment::with([
            'user:id,name,email',
            'post:id,title'
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->where('user_id', $userId);

        // Apply cursor-based pagination
        if ($lastCommentId) {
            $lastComment = Comment::find($lastCommentId);
            if ($lastComment) {
                if ($sortOrder === 'desc') {
                    $query->where('created_at', '<', $lastComment->created_at)
                        ->orWhere(function ($q) use ($lastComment) {
                            $q->where('created_at', '=', $lastComment->created_at)
                                ->where('id', '<', $lastComment->id);
                        });
                } else {
                    $query->where('created_at', '>', $lastComment->created_at)
                        ->orWhere(function ($q) use ($lastComment) {
                            $q->where('created_at', '=', $lastComment->created_at)
                                ->where('id', '>', $lastComment->id);
                        });
                }
            }
        }

        $query->orderBy($sortBy, $sortOrder)->orderBy('id', $sortOrder);

        // Get one extra record to check if there are more results
        $comments = $query->limit($limit + 1)->get();

        $hasMore = $comments->count() > $limit;
        if ($hasMore) {
            $comments = $comments->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $comments->isNotEmpty()) {
            $nextCursor = $comments->last()->id;
        }

        return [
            'data' => $comments,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * Get comments that need moderation (reported comments)
     */
    public function getReportedComments(int $perPage = 15): LengthAwarePaginator
    {
        return Comment::with([
            'user:id,name,email',
            'post:id,title',
            'reports' => function ($query) {
                $query->where('status', 'pending');
            }
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->whereHas('reports', function ($query) {
                $query->where('status', 'pending');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get comment statistics for a post
     */
    public function getPostCommentStats(int $postId): array
    {
        $stats = Comment::forPost($postId)
            ->selectRaw('
                COUNT(*) as total_comments,
                COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as top_level_comments,
                COUNT(CASE WHEN parent_id IS NOT NULL THEN 1 END) as replies
            ')
            ->first();

        return [
            'total_comments' => $stats->total_comments ?? 0,
            'top_level_comments' => $stats->top_level_comments ?? 0,
            'replies' => $stats->replies ?? 0,
        ];
    }

    /**
     * Get recent comments across all posts
     */
    public function getRecentComments(int $limit = 10): Collection
    {
        return Comment::with([
            'user:id,name,email',
            'post:id,title'
        ])
            ->withCount(['likes', 'dislikes'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search comments
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Comment::with([
            'user:id,name,email',
            'post:id,title'
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->where('content', 'LIKE', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Search comments with load more functionality
     */
    public function searchLoadMore(string $query, int $limit = 15, ?int $lastCommentId = null): array
    {
        $queryBuilder = Comment::with([
            'user:id,name,email',
            'post:id,title'
        ])
            ->withCount(['likes', 'dislikes', 'reports', 'replies'])
            ->where('content', 'LIKE', "%{$query}%");

        // Apply cursor-based pagination
        if ($lastCommentId) {
            $lastComment = Comment::find($lastCommentId);
            if ($lastComment) {
                $queryBuilder->where('created_at', '<', $lastComment->created_at)
                    ->orWhere(function ($q) use ($lastComment) {
                        $q->where('created_at', '=', $lastComment->created_at)
                            ->where('id', '<', $lastComment->id);
                    });
            }
        }

        $queryBuilder->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        // Get one extra record to check if there are more results
        $comments = $queryBuilder->limit($limit + 1)->get();

        $hasMore = $comments->count() > $limit;
        if ($hasMore) {
            $comments = $comments->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $comments->isNotEmpty()) {
            $nextCursor = $comments->last()->id;
        }

        return [
            'data' => $comments,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }
}
