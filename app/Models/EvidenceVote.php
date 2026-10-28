<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_id',
        'user_id',
        'vote_type',
        'comment',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(CitizenEvidence::class, 'evidence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
