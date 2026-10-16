<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role'])]
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
     * Menggunakan allowlist daftar email admin resmi yang diizinkan secara eksplisit
     * melalui konfigurasi auth.admin_emails (environment variable: ADMIN_EMAILS)
     * atau atribut role 'admin'.
     */
    public function isAdmin(): bool
    {
        /** @var list<string> $adminEmails */
        $adminEmails = config('auth.admin_emails', [
            'admin@palcomtech.ac.id',
            'admin@example.com',
        ]);

        // If admin_emails is explicitly set to empty array, revoke admin
        if (config()->has('auth.admin_emails') && empty($adminEmails)) {
            return false;
        }

        if (($this->role ?? '') === 'admin') {
            return true;
        }

        $email = strtolower(trim((string) $this->email));

        if ($email === '') {
            return false;
        }

        $allowlist = array_map(fn (string $e) => strtolower(trim($e)), (array) $adminEmails);

        return in_array($email, $allowlist, true);
    }

    /**
     * Tentukan apakah pengguna memiliki role BAAK.
     * Hanya pengguna dengan role eksplisit 'baak' yang diakui.
     */
    public function isBaak(): bool
    {
        return ($this->role ?? '') === 'baak';
    }

    /**
     * Tentukan apakah pengguna memiliki role AO (Academic Officer / Front Officer).
     * Hanya pengguna dengan role eksplisit 'ao' yang diakui.
     */
    public function isAo(): bool
    {
        return ($this->role ?? '') === 'ao';
    }

    /**
     * Tentukan apakah pengguna memiliki role Instruktur.
     * Hanya pengguna dengan role eksplisit 'instructor' yang diakui.
     */
    public function isInstructor(): bool
    {
        return ($this->role ?? '') === 'instructor';
    }

    /**
     * Tentukan apakah pengguna berwenang menambah atau mengedit instruktur (Admin dan BAAK).
     */
    public function canManageInstructors(): bool
    {
        return $this->isAdmin() || $this->isBaak();
    }

    /**
     * Tentukan apakah pengguna berwenang menambah atau mengedit data ruangan (Admin dan BAAK).
     */
    public function canManageRooms(): bool
    {
        return $this->isAdmin() || $this->isBaak();
    }

    /**
     * Riwayat log aktivitas yang dilakukan oleh pengguna ini.
     *
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    /**
     * Proposal penjadwalan yang diajukan oleh pengguna ini (AO).
     */
    public function submittedProposals(): HasMany
    {
        return $this->hasMany(ScheduleProposal::class, 'requested_by');
    }

    /**
     * Keputusan persetujuan yang ditetapkan oleh pengguna ini (BAAK).
     */
    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ScheduleApprovalDecision::class, 'decision_by');
    }

    /**
     * Profil instruktur yang tertaut ke akun pengguna ini (jika bertindak sebagai instruktur).
     */
    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class);
    }
}
