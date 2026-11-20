# Certificados en segundo plano

La confirmación POST con sesión administrativa y CSRF guarda una tanda inmutable en el almacenamiento privado `ecap_certificados/lotes`, fuera de public_html. El token hace idempotente la confirmación. Se conserva edición, tipo de destinatario, administrador, revisión de plantilla y huellas de las personas seleccionadas; nunca se seleccionan alumnos adicionales automáticamente.

El servidor libera la sesión, responde y continúa mediante FastCGI/LiteSpeed. Cada resultado se guarda con reemplazo atómico y bloqueo exclusivo por tanda. La consulta de progreso es sólo lectura y no envía correos. El worker usa los servicios de certificados existentes y sus bloqueos por destinatario.

El cron PHP 8.3 de `services/enviar-novedades.php --run` también recupera pendientes, con un presupuesto de 120 segundos para certificados por ejecución. Comprobar que ese cron esté activo cada cinco minutos al desplegar. Un intento que quedó procesando se concilia contra el resultado de envío guardado; si no está confirmado se marca para revisión, sin reenviarlo automáticamente. Los destinatarios que todavía no se intentaron continúan.

Una selección anterior que sólo existía en la sesión no se convierte automáticamente en tanda: requiere confirmar el envío. Los certificados emitidos conservan su PDF original. No se envían pruebas a destinatarios reales para verificar la cola.

Validación: `php tests/certificados-cola.php`. Simula fallos, procesos que terminan abruptamente, concurrencia, doble confirmación y recuperación sin SMTP real.
