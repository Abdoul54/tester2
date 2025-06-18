<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;


class Post extends Model
{
    protected $fillable = ['title', 'content', 'user_id', 'description', 'category', 'tags', 'thumbnail'];

    protected $casts = [
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the full URL for the thumbnail
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail) {
            return null;
        }

        return Storage::disk('s3')->url($this->thumbnail);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
