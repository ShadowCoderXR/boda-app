<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name', 'parent_id', 'user_id'];

    public function parent()
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Group::class, 'parent_id');
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
