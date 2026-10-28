<h1>Alerta SOS de RESGUARDA</h1>

<p>Se registró una alerta de emergencia.</p>

<ul>
    <li>Identificador: #{{ $alertId }}</li>
    <li>Fecha y hora: {{ $createdAt }}</li>
</ul>

@if ($latitude !== null && $longitude !== null)
    <p>Ubicación compartida: {{ $latitude }}, {{ $longitude }}</p>
@endif

<p>Si estás en condiciones, contacta a la persona y a los servicios de emergencia locales.</p>
