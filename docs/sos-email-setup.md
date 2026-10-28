# Configuración de correo SOS

Las notificaciones SOS usan la API HTTP de Resend para poder repetir una solicitud con la misma clave de idempotencia. SMTP no ofrece esa garantía en todos los casos; por eso el correo SOS no usa el transporte SMTP ni el mailer global de Laravel.

## Desarrollo sin correo real

Mantén estos valores para guardar alertas sin contactar destinatarios:

```dotenv
MAIL_MAILER=log
SOS_EMAIL_PROVIDER=log
RESEND_API_KEY=
QUEUE_CONNECTION=database
```

La aplicación registra la alerta y muestra el estado de la notificación; las pruebas automatizadas sustituyen la API con `Http::fake()`.

## Activar Resend

1. Crea una clave API en Resend y guárdala en el gestor de secretos del entorno. No la publiques ni la agregues a Git.
2. Verifica en Resend el dominio o remitente autorizado que utilizarás.
3. Configura los valores reales en el entorno de ejecución:

   ```dotenv
   SOS_EMAIL_PROVIDER=resend
   RESEND_API_KEY=<clave-proporcionada-por-Resend>
   MAIL_FROM_ADDRESS=<remitente-verificado>
   MAIL_FROM_NAME=RESGUARDA
   QUEUE_CONNECTION=database
   ```

4. Conserva `MAIL_MAILER=log`: únicamente el flujo SOS llama directamente a la API de Resend; los demás correos de Laravel no cambian de proveedor.
5. Si la configuración está cacheada, ejecuta `C:\php84\php.exe artisan config:clear`.
6. Inicia el trabajador para procesar notificaciones pendientes:

   ```powershell
   C:\php84\php.exe artisan queue:work database --queue=default --tries=48 --timeout=30
   ```

La cola permite hasta 48 intentos por entrega, con pausas de 30, 60, 120, 300, 600, 900 y luego 1.800 segundos entre intentos. El tiempo acumulado de espera es inferior a 23 horas; el job además bloquea cada solicitud al alcanzar esa ventana y el trabajador debe usar `--tries=48` para no truncar antes la política. Resend conserva las claves de idempotencia durante 24 horas, así que nunca se envía una solicitud nueva después del límite interno de 23 horas.

Los errores de conexión, HTTP 408/5xx y `concurrent_idempotent_requests` se tratan como resultados potencialmente ambiguos y se reintentan con la misma clave y el mismo contenido. El payload se captura una vez por entrega y se cifra en la base de datos para que cambios posteriores de configuración o plantilla no alteren el contenido del reintento. `invalid_idempotent_request` y un HTTP 409 cuyo código estructurado no se reconoce no se reintentan automáticamente. Se registran solo el ID interno de la entrega, el estado HTTP y un código seguro; el estado final es `uncertain` cuando no es posible confirmar si Resend aceptó el mensaje. Un resultado `failed` representa un rechazo conocido; `accepted` significa que Resend aceptó la solicitud, no que el destinatario la recibió o leyó.

La clave opaca de cada acción SOS pendiente se conserva en `localStorage`, separada por el ID de la cuenta autenticada. Así se reutiliza después de recargar, cerrar y volver a abrir la pestaña o continuar en otra pestaña del mismo navegador. Si una versión anterior dejó una clave pendiente en el `sessionStorage` de una pestaña abierta, se migra a `localStorage` al volver a la página. No se guarda allí ubicación, correo ni contenido de la solicitud, y la clave no se registra en logs. Se elimina al recibir una respuesta exitosa; después, iniciar deliberadamente otra acción genera una clave nueva. Cerrar sesión no elimina una acción pendiente, pero otra cuenta no puede reutilizarla porque utiliza otro espacio de almacenamiento. Iniciar explícitamente otra alerta mientras queda una acción sin confirmar puede duplicar el aviso si el primer intento sí fue aceptado. Si el almacenamiento no está disponible, la interfaz no envía la solicitud sin idempotencia.

Las entregas `uncertain` requieren revisión manual: un administrador autorizado debe comparar el destinatario, la hora y el identificador de alerta con el panel de Resend. Si confirma aceptación, registra el ID del mensaje del proveedor; el estado pasa a `accepted`, que sigue sin implicar entrega o lectura. Si Resend confirma que no aceptó el mensaje, puede registrar esa constatación como `failed`. La revisión guarda quién y cuándo tomó la decisión. Ninguna de las opciones vuelve a encolar el correo. Si la evidencia no permite determinar el resultado, se deja la entrega en `uncertain` y se escala al proveedor; no se reenvía porque el primer mensaje pudo haber salido.

El panel de revisión está en `/admin/sos-deliveries` y solo admite usuarios administradores. Muestra el correo del destinatario únicamente a esos operadores para poder contrastar el registro con Resend; no expone las coordenadas ni el contenido del mensaje. La revisión de aceptación requiere el ID de mensaje del proveedor.

El endpoint valida que exista el esquema base de idempotencia para registrar una alerta. Si falta alguna migración necesaria para correo (incluida la instantánea cifrada del contenido), aún registra el SOS, lo marca `unavailable`, no crea entregas y no despacha trabajos; muestra un mensaje genérico indicando que las migraciones están pendientes. Si falta el esquema base de `sos_alerts` o su clave de idempotencia, no es posible guardar el SOS de forma segura y el endpoint responde con indisponibilidad.

El rollback de la migración de correo se bloquea mientras existan direcciones guardadas; exporta y migra los datos explícitamente antes de considerar retirar esa columna. El rollback de idempotencia se bloquea mientras haya alertas con claves guardadas y conserva sin cambios sus estados. El rollback de payload se bloquea mientras haya instantáneas guardadas, ya que eliminarlas permitiría que una versión anterior reconstruyera contenido distinto para una clave ya utilizada. La nueva migración de revisión también bloquea el rollback si hay entregas revisadas, para preservar la atribución y el resultado manual. En entornos compartidos o producción, prefiere una migración correctiva hacia delante en lugar de eliminar datos de seguimiento.

Antes de habilitarlo en producción, prueba con un remitente verificado y un buzón de prueba autorizado. La ubicación se incluye solo si el usuario concedió permiso y el navegador la proporcionó.
