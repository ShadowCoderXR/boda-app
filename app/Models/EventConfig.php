<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventConfig extends Model
{
    protected $fillable = [
        'wedding_date', 'wedding_time', 'venue_name', 'venue_address', 'venue_map_link', 'reception_details', 'dress_code', 'registry_info', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'wedding_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
