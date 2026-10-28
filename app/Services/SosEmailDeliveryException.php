<?php

namespace App\Services;

use RuntimeException;

class SosEmailDeliveryException extends RuntimeException
{
    public function __construct(
        public readonly bool $retryable,
        public readonly string $failureCode,
        public readonly bool $outcomeUncertain = false,
    ) {
        parent::__construct('No se pudo procesar la notificación SOS.');
    }
}
