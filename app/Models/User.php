<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Tentukan apakah pengguna memiliki hak akses administrator.
     * Mekanisme pembatasan sementara yang aman dan terdokumentasi
     * berbasis konfigurasi email admin, format email, dan atribut role jika ada.
     */
    public function isAdmin(): bool
    {
        if (isset($this->role) && $this->role === 'admin') {
            return true;
        }

        if (isset($this->is_admin) && (bool) $this->is_admin) {
            return true;
        }

        /** @var list<string> $adminEmails */
        $adminEmails = config('auth.admin_emails', [
            'admin@palcomtech.ac.id',
            'admin@example.com',
        ]);

        $email = strtolower($this->email);

        if (in_array($email, array_map('strtolower', (array) $adminEmails), true)) {
            return true;
        }

        return str_starts_with($email, 'admin@')
            || str_starts_with($email, 'admin_')
            || str_starts_with($email, 'admin.');
    }
}
