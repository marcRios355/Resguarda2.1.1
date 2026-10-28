<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateMessage extends Model
{
    /** @use HasFactory<Factory<PrivateMessage>> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'recipient_id',
        'lost_found_item_id',
        'message',
        'attachment_path',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<LostFoundItem, $this> */
    public function lostFoundItem(): BelongsTo
    {
        return $this->belongsTo(LostFoundItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
