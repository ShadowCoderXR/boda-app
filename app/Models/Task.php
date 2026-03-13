<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title', 'description', 'notes', 'due_date', 'priority', 'is_completed', 'sponsor_id', 'assigned_to_id', 'user_id', 'category_id', 'external_guest_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_completed' => 'boolean',
        ];
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'asc');
    }

    public function externalGuest()
    {
        return $this->belongsTo(Guest::class, 'external_guest_id');
    }
}
