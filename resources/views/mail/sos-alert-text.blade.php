ALERTA SOS DE RESGUARDA

Se registró una alerta de emergencia.
Identificador: #{{ $alertId }}
Fecha y hora: {{ $createdAt }}
@if ($latitude !== null && $longitude !== null)
Ubicación compartida: {{ $latitude }}, {{ $longitude }}
@endif

Si estás en condiciones, contacta a la persona y a los servicios de emergencia locales.
