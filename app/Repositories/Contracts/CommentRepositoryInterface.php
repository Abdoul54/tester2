<?php
// app/Repositories/Contracts/CommentRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CommentRepositoryInterface
{
    /**
     * Get paginated comments for a post
     */
    public function getCommentsForPost(int $postId, int $perPage = 15, string $sortBy = 'created_at', string $sortOrder = 'desc'): LengthAwarePaginator;

    /**
     * Get comments for a post using load more functionality
     */
    public function getCommentsForPostLoadMore(int $postId, int $limit = 15, ?int $lastCommentId = null, string $sortBy = 'created_at', string $sortOrder = 'desc'): array;

    /**
     * Get all comments for a post without pagination
     */
    public function getAllCommentsForPost(int $postId): Collection;

    /**
     * Create a new comment
     */
    public function create(array $data): Comment;

    /**
     * Find a comment by ID
     */
    public function findById(int $id): ?Comment;

    /**
     * Update a comment
     */
    public function update(int $id, array $data): Comment;

    /**
     * Delete a comment (soft delete)
     */
    public function delete(int $id): bool;

    /**
     * Restore a soft-deleted comment
     */
    public function restore(int $id): bool;

    /**
     * Permanently delete a comment
     */
    public function forceDelete(int $id): bool;

    /**
     * Get comments by user (paginated)
     */
    public function getCommentsByUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get comments by user with load more functionality
     */
    public function getCommentsByUserLoadMore(int $userId, int $limit = 15, ?int $lastCommentId = null, string $sortBy = 'created_at', string $sortOrder = 'desc'): array;

    /**
     * Get reported comments for moderation
     */
    public function getReportedComments(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get comment statistics for a post
     */
    public function getPostCommentStats(int $postId): array;

    /**
     * Get recent comments across all posts
     */
    public function getRecentComments(int $limit = 10): Collection;

    /**
     * Search comments (paginated)
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator;

    /**
     * Search comments with load more functionality
     */
    public function searchLoadMore(string $query, int $limit = 15, ?int $lastCommentId = null): array;
}
