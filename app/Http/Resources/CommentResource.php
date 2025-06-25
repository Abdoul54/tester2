<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = Auth::id();

        return [
            'id' => $this->id,
            'content' => $this->content,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,

            // User information
            'user' => new UserResource($this->whenLoaded('user')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),

            // Edit information
            'is_edited' => $this->is_edited ?? false,
            'edited_at' => $this->edited_at?->toISOString(),
            'edited_at_human' => $this->edited_at?->diffForHumans(),

            // Comment structure
            'is_top_level' => $this->is_top_level ?? is_null($this->parent_id),
            'is_reply' => $this->is_reply ?? !is_null($this->parent_id),

            // Interaction counts
            'likes_count' => $this->likes_count ?? 0,
            'dislikes_count' => $this->dislikes_count ?? 0,
            'reports_count' => $this->reports_count ?? 0,
            'replies_count' => $this->replies_count ?? 0,

            // User-specific interactions (only if authenticated)
            'user_liked' => $userId ? $this->isLikedBy($userId) : false,
            'user_disliked' => $userId ? $this->isDislikedBy($userId) : false,
            'user_reported' => $userId ? $this->isReportedBy($userId) : false,

            // Permissions (only if authenticated)
            'can_edit' => $userId ? $this->canBeEditedBy($userId) : false,
            'can_delete' => $userId ? $this->canBeDeletedBy($userId) : false,
            'is_author' => $userId ? $this->isOwnedBy($userId) : false,

            // Nested replies (basic for now)
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
