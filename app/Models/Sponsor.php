<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    protected $fillable = ['guest_id', 'role', 'details', 'user_id'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
