<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'website', 'price', 'price_quote', 'status', 'sponsor_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
