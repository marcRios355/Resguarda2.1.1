<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
#[Fillable([
    'name',
    'last_name',
    'username',
    'email',
    'password',
    'phone',
    'avatar',
    'bio',
    'role',
    'reliability_level',
    'reputation_score',
    'status',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
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
            'two_factor_confirmed_at' => 'datetime',
            'reputation_score' => 'integer',
        ];
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function reportConfirmations()
    {
        return $this->hasMany(ReportConfirmation::class);
    }

    public function communityMessages()
    {
        return $this->hasMany(CommunityMessage::class);
    }

    /** @return HasMany<EmergencyContact, $this> */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /** @return HasMany<SosAlert, $this> */
    public function sosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class);
    }

    public function citizenEvidences()
    {
        return $this->hasMany(CitizenEvidence::class);
    }

    public function evidenceVotes()
    {
        return $this->hasMany(EvidenceVote::class);
    }

    public function needHelpRequests()
    {
        return $this->hasMany(NeedHelpRequest::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'user_one_id')
            ->orWhere('user_two_id', $this->id);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
