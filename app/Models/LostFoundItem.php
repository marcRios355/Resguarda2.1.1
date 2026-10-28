<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $event_date */
class LostFoundItem extends Model
{
    public const CATEGORIES = [
        'electronics' => 'Electrónica',
        'documents' => 'Documentos',
        'keys' => 'Llaves',
        'clothing' => 'Ropa',
        'accessories' => 'Accesorios',
        'pets' => 'Mascotas',
        'other' => 'Otros',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'title',
        'description',
        'event_date',
        'approximate_zone',
        'photo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
