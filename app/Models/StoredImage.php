<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fotografía subida por un usuario, guardada en la base de datos para que
 * persista aunque el servidor reinicie o se vuelva a desplegar.
 *
 * @property string $path
 * @property string $mime_type
 * @property int $size
 */
class StoredImage extends Model
{
    protected $fillable = [
        'path',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];
}
