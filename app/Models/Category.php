<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'color', 'user_id'];

    public function guests()
    {
        return $this->belongsToMany(Guest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
