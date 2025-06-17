<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Always require email verification and domain check
        $hasValidEmail = str_ends_with($this->email, '@pixalink.io') && $this->hasVerifiedEmail();

        // In local environment, allow access but still log for audit
        if (app()->isLocal()) {
            if (! $hasValidEmail) {
                \Log::warning('Local environment: User accessed panel without proper domain email', [
                    'user_id' => $this->id,
                    'email' => $this->email,
                ]);
            }

            return $hasValidEmail;
        }

        return $hasValidEmail;
    }
}
