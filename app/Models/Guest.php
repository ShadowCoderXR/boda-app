<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasUuids, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'group_id', 'rsvp_status', 'rsvp_details', 'user_id',
        'is_representative', 'representative_id', 'extra_spots', 'seating_table_id',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            if (empty($guest->slug)) {
                $baseSlug = Str::slug($guest->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$counter++;
                }

                $guest->slug = $slug;
            }
        });

        static::deleting(function (Guest $guest) {
            // Cascade delete members if this is a representative
            if ($guest->is_representative) {
                $guest->members()->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'rsvp_details' => 'array',
            'is_representative' => 'boolean',
            'extra_spots' => 'integer',
        ];
    }

    public function members()
    {
        return $this->hasMany(Guest::class, 'representative_id');
    }

    public function representative()
    {
        return $this->belongsTo(Guest::class, 'representative_id');
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

    public function seatingTable()
    {
        return $this->belongsTo(SeatingTable::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'external_guest_id');
    }

}
