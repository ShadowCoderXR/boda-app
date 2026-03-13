<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'temporary_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isNovio(): bool
    {
        return $this->role === UserRole::Novio;
    }

    public function isNovia(): bool
    {
        return $this->role === UserRole::Novia;
    }

    public function isPadrino(): bool
    {
        return in_array($this->role, [
            UserRole::Padrino1,
            UserRole::Padrino2,
            UserRole::Padrino3,
        ]);
    }

    public function isMadrina(): bool
    {
        return in_array($this->role, [
            UserRole::Madrina1,
            UserRole::Madrina2,
            UserRole::Madrina3,
        ]);
    }

    public function isColaborador(): bool
    {
        return $this->role === UserRole::Colaborador;
    }

    /**
     * Get the primary guest record for this user
     */
    public function guest()
    {
        return $this->hasOne(Guest::class, 'user_id')->where('representative_id', null);
    }

    /**
     * Get all guests managed by this user
     */
    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function sponsors()
    {
        return $this->hasMany(Sponsor::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function inspirationItems()
    {
        return $this->hasMany(InspirationItem::class);
    }
}
