# Anuncios de actividades

Publicada el 16/11/2026. Tablas creadas y prueba única autorizada enviada a ivanmontano87@gmail.com; aceptación confirmada por el transporte. Borrador de edición 5 guardado, 224 destinatarios elegibles. Ninguna campaña iniciada. Herramientas temporales de instalación y prueba retiradas tras su uso. El usuario confirmó recepción y diseño correctos. Cron guardado cada cinco minutos con PHP 8.3; Hostinger muestra la salida vacía, por lo que su primera ejecución aún no está verificada.

## Operación

Superadministrador > edición > Anunciar actividad. Guardar asunto e invitación, revisar vista previa y cantidad, confirmar una única campaña por edición. Botón centrado; imagen original y fecha como texto separado. Cada correo incluye baja individual; abrir el enlace no da de baja hasta confirmarlo.

Solo personas activas con último consentimiento email_marketing=1 y email válido. Una copia por email normalizado. Baja prevalece para todos los perfiles de esa dirección y no afecta comunicaciones transaccionales. Se vuelve a validar antes de cada correo. Los cambios de email dejan el destinatario omitido para revisión, sin enviar a otra dirección no revisada.

Contenido y destinatarios se congelan al confirmar. Repetir el POST o editar/publicar la actividad no crea otra campaña. Al confirmar, continuar o reintentar se inicia el procesamiento inmediato de todos los pendientes, sin límite propio diario ni pausas programadas. La sesión se libera y FPM/LiteSpeed finaliza la respuesta para permitir consultar el progreso; en otros servidores la petición procesa antes de redirigir. Siguen vigentes los límites reales del proveedor. Ante error, se detiene la tanda. No se reintentan resultados ambiguos automáticamente. Los errores explícitos permiten reintento manual; los aceptados nunca se reenvían por esta función. Se puede pausar la campaña; un correo en curso puede terminar.

## Operación y verificaciones

1. Backup y ejecutar database/migrations/20261116_novedades.sql. Crea cinco tablas independientes; no migra ni presume consentimientos.
2. Publicar los archivos y revisar la pantalla autenticada. Confirmar que el remitente real tiene capacidad y reputación para anuncios masivos.
3. Revisar el mail en móvil, Gmail y Outlook con una prueba a una dirección autorizada antes de enviar a la base. La placa usa imagen de fondo y fecha textual: la imagen de fondo no está garantizada en Outlook clásico; el título y la fecha textual debajo mantienen la información.
4. El envío comienza desde la confirmación del panel. El cron es un respaldo para pendientes tras una interrupción. Para procesar sin mantener abierta la pantalla, agregar un cron con PHP 8.3: `/opt/alt/php83/usr/bin/php /home/u736192581/domains/ecap.com.ar/public_html/services/enviar-novedades.php --run`, cada 5 minutos. No modificar los cron de recordatorios.
5. No crear una campaña real hasta aprobar su contenido y destinatarios. El enlace público nunca autoriza reenvíos.

## Validación realizada

- PHP lint y git diff --check.
- tests/novedades.php: consentimiento más reciente, deduplicación, baja, inactivos, inválidos, escape de contenido y fecha independiente.
- tests/novedades-integracion.php: MySQL 5.7 efímero por socket, direcciones example.test, transporte local sin SMTP; guarda borrador, rechaza revisión obsoleta, congela cola, bloquea duplicados, baja antes de envío, captura local, resultados persistidos y protección contra reintento exitoso.
- Prueba real recibida y aprobada por el usuario. Cron configurado; pendiente comprobar su primera salida. Outlook clásico no verificado. No se garantiza entrega a bandeja de entrada.
