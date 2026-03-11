<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspirationItem extends Model
{
    protected $fillable = ['type', 'category', 'content', 'description', 'is_favorite', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
