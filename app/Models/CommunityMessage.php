<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMessage extends Model
{
    /** @use HasFactory<Factory<CommunityMessage>> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'district_id',
        'reply_to_id',
        'message',
        'attachment_path',
        'is_reported',
        'report_count',
        'is_hidden',
    ];

    protected $casts = [
        'is_reported' => 'boolean',
        'report_count' => 'integer',
        'is_hidden' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** @return BelongsTo<CommunityMessage, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(CommunityMessage::class, 'reply_to_id');
    }
}
