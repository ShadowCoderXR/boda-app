<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'subgroup_id', 'is_default', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public static function getDefault(int $userId): self
    {
        return self::firstOrCreate(
            ['is_default' => true, 'user_id' => $userId, 'subgroup_id' => null],
            ['name' => 'General']
        );
    }

    public function subgroup()
    {
        return $this->belongsTo(Subgroup::class);
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
