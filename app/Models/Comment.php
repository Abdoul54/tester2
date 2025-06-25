<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'is_edited',
        'edited_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_edited' => 'boolean',
    ];

    // Basic relationships
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    // Interaction relationships
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_likes')
            ->withTimestamps();
    }

    public function dislikes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_dislikes')
            ->withTimestamps();
    }

    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_reports')
            ->withTimestamps()
            ->withPivot(['reason', 'description', 'status']);
    }

    // Simple scopes
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForPost($query, $postId)
    {
        return $query->where('post_id', $postId);
    }

    // Helper methods for user interactions
    public function isLikedBy($user = null): bool
    {
        $userId = $user instanceof User ? $user->id : ($user ?: Auth::id());
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function isDislikedBy($user = null): bool
    {
        $userId = $user instanceof User ? $user->id : ($user ?: Auth::id());
        return $this->dislikes()->where('user_id', $userId)->exists();
    }

    public function isReportedBy($user = null): bool
    {
        $userId = $user instanceof User ? $user->id : ($user ?: Auth::id());
        return $this->reports()->where('user_id', $userId)->exists();
    }

    public function isOwnedBy($user = null): bool
    {
        $userId = $user instanceof User ? $user->id : ($user ?: Auth::id());
        return $this->user_id === $userId;
    }

    public function canBeEditedBy($user = null): bool
    {
        if (!$this->isOwnedBy($user)) {
            return false;
        }
        // Allow editing within 15 minutes of creation
        return $this->created_at->diffInMinutes(now()) <= 15;
    }

    public function canBeDeletedBy($user = null): bool
    {
        return $this->isOwnedBy($user);
    }

    // Action methods
    public function toggleLike($userId = null): array
    {
        $userId = $userId ?: Auth::id();

        // Remove dislike if exists
        $this->dislikes()->detach($userId);

        if ($this->isLikedBy($userId)) {
            $this->likes()->detach($userId);
            $action = 'removed_like';
        } else {
            $this->likes()->attach($userId);
            $action = 'liked';
        }

        return [
            'action' => $action,
            'likes_count' => $this->likes()->count(),
            'dislikes_count' => $this->dislikes()->count(),
            'user_liked' => $this->isLikedBy($userId),
            'user_disliked' => $this->isDislikedBy($userId)
        ];
    }

    public function toggleDislike($userId = null): array
    {
        $userId = $userId ?: Auth::id();

        // Remove like if exists
        $this->likes()->detach($userId);

        if ($this->isDislikedBy($userId)) {
            $this->dislikes()->detach($userId);
            $action = 'removed_dislike';
        } else {
            $this->dislikes()->attach($userId);
            $action = 'disliked';
        }

        return [
            'action' => $action,
            'likes_count' => $this->likes()->count(),
            'dislikes_count' => $this->dislikes()->count(),
            'user_liked' => $this->isLikedBy($userId),
            'user_disliked' => $this->isDislikedBy($userId)
        ];
    }

    public function addReport($userId = null, $reason = null, $description = null): bool
    {
        $userId = $userId ?: Auth::id();

        if ($this->isReportedBy($userId)) {
            return false;
        }

        $this->reports()->attach($userId, [
            'reason' => $reason,
            'description' => $description,
            'status' => 'pending'
        ]);

        return true;
    }

    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now()
        ]);
    }

    // Accessors
    public function getIsTopLevelAttribute(): bool
    {
        return is_null($this->parent_id);
    }

    public function getIsReplyAttribute(): bool
    {
        return !is_null($this->parent_id);
    }
}
