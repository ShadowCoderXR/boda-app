<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingTable extends Model
{
    /** @use HasFactory<\Database\Factories\SeatingTableFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'capacity',
        'user_id',
    ];

    /**
     * Get the user that owns the seating table.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the guests assigned to this seating table.
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }
}
