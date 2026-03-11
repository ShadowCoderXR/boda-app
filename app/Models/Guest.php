<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'group_id', 'rsvp_status', 'rsvp_details', 'user_id'
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            if (empty($guest->slug)) {
                $guest->slug = Str::slug($guest->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'rsvp_details' => 'array',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function sponsor()
    {
        return $this->hasOne(Sponsor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
