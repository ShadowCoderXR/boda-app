<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspirationItem extends Model
{
    protected $fillable = ['type', 'category_id', 'content', 'description', 'is_favorite', 'metadata', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isLink(): bool
    {
        return $this->type === 'link';
    }

    public function isColor(): bool
    {
        return $this->type === 'color';
    }

    public function getThumbnail(): ?string
    {
        return $this->metadata['thumbnail'] ?? null;
    }

    public function getLinkTitle(): ?string
    {
        return $this->metadata['title'] ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
