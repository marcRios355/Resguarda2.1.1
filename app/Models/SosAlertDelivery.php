<?php

namespace App\Models;

use Database\Factories\SosAlertDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sos_alert_id
 * @property string $recipient_hash
 * @property string $recipient_email
 * @property array<string, mixed>|null $request_payload
 * @property string $status
 * @property int $attempt_count
 * @property Carbon|null $first_attempted_at
 * @property Carbon|null $accepted_at
 * @property string|null $provider_message_id
 * @property string|null $failure_code
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $review_outcome
 */
class SosAlertDelivery extends Model
{
    /** @use HasFactory<SosAlertDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'sos_alert_id',
        'recipient_hash',
        'recipient_email',
        'request_payload',
        'status',
        'attempt_count',
        'first_attempted_at',
        'accepted_at',
        'provider_message_id',
        'failure_code',
        'reviewed_at',
        'reviewed_by',
        'review_outcome',
    ];

    protected function casts(): array
    {
        return [
            'recipient_email' => 'encrypted',
            'request_payload' => 'encrypted:array',
            'first_attempted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SosAlert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }
}
