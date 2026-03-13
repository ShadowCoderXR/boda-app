<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'type', 'is_default', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public static function getDefault(string $type, int $userId): self
    {
        return self::firstOrCreate(
            ['type' => $type, 'is_default' => true, 'user_id' => $userId],
            ['name' => 'General', 'color' => 'sage']
        );
    }

    public function guests()
    {
        return $this->belongsToMany(Guest::class);
    }

    public function inspirationItems()
    {
        return $this->hasMany(InspirationItem::class);
    }

    public function vendors()
    {
        return $this->belongsToMany(Vendor::class);
    }

    public function sponsors()
    {
        return $this->hasMany(Sponsor::class, 'role_category_id'); // If using roles as categories
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
