<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $unread_count Mensajes sin leer del usuario actual (via withCount).
 */
class Conversation extends Model
{
    /** @use HasFactory<Factory<Conversation>> */
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /** @return BelongsTo<User, $this> */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /** @return HasMany<PrivateMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(PrivateMessage::class);
    }

    public function hasParticipant(int $userId): bool
    {
        return in_array($userId, [$this->user_one_id, $this->user_two_id], true);
    }

    public function otherUser(int $currentUserId): User
    {
        return $this->user_one_id === $currentUserId ? $this->userTwo : $this->userOne;
    }
}
