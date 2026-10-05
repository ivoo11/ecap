<?php

declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/MailService.php';

/*
 * =========================================================
 * ECAP · JOB DE RECORDATORIOS
 * =========================================================
 *
 * MODOS DISPONIBLES
 *
 * --dry-run
 *   Analiza todo.
 *   No envía emails.
 *   No escribe en comunicaciones_transaccionales.
 *
 * --test
 *   Ejecuta el circuito real.
 *   Envía únicamente al email indicado en --email.
 *   Registra el resultado en comunicaciones_transaccionales.
 *
 * Ejemplos:
 *
 * ECAP_ENV=local php app/jobs/enviar-recordatorios.php \
 * --dry-run \
 * --fecha=2026-10-08
 *
 * ECAP_ENV=local php app/jobs/enviar-recordatorios.php \
 * --test \
 * --fecha=2026-10-08 \
 * --email=tu@email.com
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este proceso sólo puede ejecutarse por CLI.\n");
}

$opciones = getopt('', [
    'dry-run',
    'test',
    'run',
    'fecha:',
    'email:'
]);

$esDryRun = isset($opciones['dry-run']);
$esTest   = isset($opciones['test']);
$esRun    = isset($opciones['run']);

$cantidadModos =
    (int) $esDryRun +
    (int) $esTest +
    (int) $esRun;

if ($cantidadModos !== 1) {
    exit(
        "ERROR: indicá exactamente uno de estos modos: " .
        "--dry-run, --test o --run.\n"
    );
}

$emailTest = '';

if ($esTest) {

    $emailTest = trim(
        (string) ($opciones['email'] ?? '')
    );

    if (
        $emailTest === '' ||
        !filter_var($emailTest, FILTER_VALIDATE_EMAIL)
    ) {
        exit(
            "ERROR: en modo --test debés indicar " .
            "--email=tu@email.com\n"
        );
    }
}

/*
 * Fecha de trabajo.
 *
 * En --run siempre se trabaja con la fecha real de hoy.
 * --fecha queda reservado para --dry-run y --test.
 */
if ($esRun && isset($opciones['fecha'])) {
    exit(
        "ERROR: --run no admite --fecha. " .
        "El modo real siempre trabaja con la fecha de hoy.\n"
    );
}

$fechaTrabajo = $esRun
    ? date('Y-m-d')
    : ($opciones['fecha'] ?? date('Y-m-d'));

$fechaObjeto = DateTime::createFromFormat(
    '!Y-m-d',
    $fechaTrabajo
);

$erroresFecha = DateTime::getLastErrors();

if (
    $fechaObjeto === false ||
    (
        $erroresFecha !== false &&
        (
            $erroresFecha['warning_count'] > 0 ||
            $erroresFecha['error_count'] > 0
        )
    ) ||
    $fechaObjeto->format('Y-m-d') !== $fechaTrabajo
) {
    exit(
        "ERROR: fecha inválida. Usá YYYY-MM-DD.\n"
    );
}

/*
 * Ventana horaria permitida para --run.
 *
 * El cron se ejecutará a las 07:00, 07:15 y 07:30.
 * Dejamos una pequeña tolerancia para que una demora
 * de algunos segundos/minutos del servidor no bloquee
 * una ejecución válida.
 */
if ($esRun) {
    $horaActual = date('H:i');

    /*
     * Cada ventana define cuántos intentos como máximo
     * puede haber realizado una comunicación hasta ese momento.
     *
     * 07:00 → hasta 1 intento
     * 07:15 → hasta 2 intentos
     * 07:30 → hasta 3 intentos
     *
     * Esto permite recuperarnos si una ejecución anterior
     * del cron no ocurrió.
     */
    $intentosPermitidos = null;

    if ($horaActual >= '07:00' && $horaActual <= '07:09') {
        $intentosPermitidos = 1;
    } elseif ($horaActual >= '07:15' && $horaActual <= '07:24') {
        $intentosPermitidos = 2;
    } elseif ($horaActual >= '07:30' && $horaActual <= '07:39') {
        $intentosPermitidos = 3;
    }

    if ($intentosPermitidos === null) {
        exit(
            "ERROR: --run sólo puede ejecutarse en las " .
            "ventanas autorizadas de recordatorios " .
            "(07:00, 07:15 y 07:30, hora Argentina).\n"
        );
    }

    /*
     * Lock global del job.
     *
     * Evita que dos procesos --run puedan procesar
     * recordatorios simultáneamente.
     */
    $stmtLock = $pdo->query(
        "SELECT GET_LOCK('ecap_recordatorios_clase', 0)"
    );

    $lockObtenido = (int) $stmtLock->fetchColumn();

    if ($lockObtenido !== 1) {
        exit(
            "OMITIDO: ya existe otro proceso de " .
            "recordatorios en ejecución.\n"
        );
    }

    register_shutdown_function(
        static function () use ($pdo): void {
            try {
                $pdo->query(
                    "SELECT RELEASE_LOCK('ecap_recordatorios_clase')"
                );
            } catch (Throwable $e) {
                // No hacemos nada: la conexión MySQL
                // también libera el lock al cerrarse.
            }
        }
    );
}

$inicioDia = $fechaTrabajo . ' 00:00:00';
$finDia    = $fechaTrabajo . ' 23:59:59';

/*
 * Clases del día.
 */
$sqlClases = "
    SELECT
        c.id AS clase_id,
        c.edicion_id,
        c.numero AS clase_numero,
        c.titulo AS clase_titulo,
        c.fecha_inicio AS clase_fecha_inicio,

        e.modalidad,
        e.zoom_url,
        e.zoom_meeting_id,
        e.zoom_passcode,

        a.titulo AS actividad_titulo,

        (
            SELECT COUNT(*)
            FROM clases c_total
            WHERE c_total.edicion_id = e.id
        ) AS total_clases

    FROM clases c

    INNER JOIN ediciones e
        ON e.id = c.edicion_id

    INNER JOIN actividades a
        ON a.id = e.actividad_id

    WHERE
        c.fecha_inicio >= :inicio_dia
        AND c.fecha_inicio <= :fin_dia

        AND e.estado NOT IN (
            'borrador',
            'cancelada'
        )

    ORDER BY
        c.fecha_inicio ASC,
        c.id ASC
";

$stmtClases = $pdo->prepare($sqlClases);

$stmtClases->execute([
    ':inicio_dia' => $inicioDia,
    ':fin_dia' => $finDia
]);

$clases = $stmtClases->fetchAll(PDO::FETCH_ASSOC);

$mailService = ($esTest || $esRun)
    ? new MailService()
    : null;

$modoTexto = $esDryRun
    ? 'DRY RUN'
    : ($esTest ? 'TEST' : 'RUN');

echo "\n";
echo "========================================\n";
echo "ECAP · RECORDATORIOS · {$modoTexto}\n";
echo "========================================\n";
echo "Fecha simulada: {$fechaTrabajo}\n";

if ($esTest) {
    echo "Email de prueba: {$emailTest}\n";
}

echo "Clases encontradas: " . count($clases) . "\n";
echo "========================================\n\n";

$totalDestinatarios = 0;
$totalPendientes = 0;
$totalYaRegistrados = 0;
$totalEnviados = 0;
$totalErrores = 0;

foreach ($clases as $clase) {

    /*
    * En modo real nunca enviamos un recordatorio
    * si la clase ya comenzó.
    *
    * En --test y --dry-run no aplicamos este bloqueo,
    * porque necesitamos poder simular fechas.
    */
    if ($esRun) {
        $inicioClase = new DateTime(
            (string) $clase['clase_fecha_inicio']
        );

        $ahora = new DateTime();

        if ($ahora >= $inicioClase) {
            echo
                "CLASE #{$clase['clase_id']} OMITIDA: " .
                "la clase ya comenzó.\n\n";

            continue;
        }
    }
    echo "CLASE #{$clase['clase_id']}\n";
    echo "Actividad: {$clase['actividad_titulo']}\n";
    echo "Número de clase: {$clase['clase_numero']}\n";
    echo "Inicio: {$clase['clase_fecha_inicio']}\n";
    echo "Modalidad: {$clase['modalidad']}\n";

    /*
     * Inscriptos confirmados de la edición.
     */
    $sqlDestinatarios = "
        SELECT
            i.id AS inscripcion_id,

            p.id AS persona_id,
            p.nombre,
            p.apellido,
            p.email,

            ct.id AS comunicacion_id,
            ct.estado AS comunicacion_estado,
            ct.intentos AS comunicacion_intentos

        FROM inscripciones i

        INNER JOIN personas p
            ON p.id = i.persona_id

        LEFT JOIN comunicaciones_transaccionales ct
            ON ct.inscripcion_id = i.id
            AND ct.clase_id = :clase_id
            AND ct.tipo = 'recordatorio_clase'

        WHERE
            i.edicion_id = :edicion_id
            AND i.estado = 'confirmada'

        ORDER BY
            p.apellido ASC,
            p.nombre ASC
    ";

    $stmtDestinatarios = $pdo->prepare(
        $sqlDestinatarios
    );

    $stmtDestinatarios->execute([
        ':clase_id' => $clase['clase_id'],
        ':edicion_id' => $clase['edicion_id']
    ]);

    $destinatarios = $stmtDestinatarios->fetchAll(
        PDO::FETCH_ASSOC
    );

    $pendientesClase = 0;
    $registradosClase = 0;
    $enviadosClase = 0;
    $erroresClase = 0;

    foreach ($destinatarios as $destinatario) {

        $totalDestinatarios++;

        $emailDestino = $esTest
        ? $emailTest
        : trim((string) $destinatario['email']);

    /*
    * Si la comunicación ya fue enviada,
    * nunca la volvemos a enviar.
    */
    if (
        $destinatario['comunicacion_id'] !== null &&
        $destinatario['comunicacion_estado'] === 'enviado'
    ) {

        $registradosClase++;
        $totalYaRegistrados++;

        continue;
    }

        /*
    * Máximo 3 intentos por inscripción/clase.
    *
    * Si ya falló 3 veces, queda registrado en error
    * y no volvemos a intentar automáticamente.
    */
    $intentosRealizados = (int) (
        $destinatario['comunicacion_intentos'] ?? 0
    );

    if (
        $destinatario['comunicacion_id'] !== null &&
        $intentosRealizados >= 3
    ) {
        $registradosClase++;
        $totalYaRegistrados++;

        echo
            "  · OMITIDO: máximo de 3 intentos alcanzado " .
            "para inscripción " .
            $destinatario['inscripcion_id'] .
            "\n";

        continue;
    }

        /*
    * En modo real respetamos el número máximo de intentos
    * habilitado para la ventana actual.
    *
    * Ejemplos:
    * - 07:00, si ya tiene 1 intento → no hacemos otro.
    * - 07:15, si ya tiene 2 intentos → no hacemos otro.
    * - 07:30, si ya tiene 3 intentos → no hacemos otro.
    *
    * Si un cron anterior no corrió, la siguiente ventana
    * todavía puede recuperar el envío.
    */
    if (
        $esRun &&
        $intentosRealizados >= $intentosPermitidos
    ) {
        $registradosClase++;
        $totalYaRegistrados++;

        echo
            "  · OMITIDO: ya alcanzó los intentos permitidos " .
            "para esta ventana (inscripción " .
            $destinatario['inscripcion_id'] .
            ")\n";

        continue;
    }

    /*
    * Si existe pero quedó en error,
    * reutilizamos ese mismo registro
    * para hacer un nuevo intento.
    */
    $esReintento =
        $destinatario['comunicacion_id'] !== null &&
        in_array(
            $destinatario['comunicacion_estado'],
            ['error', 'pendiente'],
            true
        );

    /*
    * Todo lo que llegó hasta acá necesita
    * ser procesado: nuevo o reintento.
    */
    $pendientesClase++;
    $totalPendientes++;

    /*
    * En dry-run solamente contamos.
    * No escribimos en BD ni enviamos.
    */
    if ($esDryRun) {
        continue;
    }

        /*
         * TEST
         *
         * Creamos primero el registro pendiente.
         *
         * La UNIQUE KEY de la tabla actúa como segunda
         * defensa frente a duplicados.
         */
        if ($esReintento) {

            /*
            * Ya existe una comunicación fallida.
            * Conservamos el mismo registro y su historial
            * de intentos.
            */
            $comunicacionId = (int) $destinatario['comunicacion_id'];

        } else {

            /*
            * Primera vez que procesamos esta comunicación.
            */
            try {

                $stmtInsert = $pdo->prepare("
                    INSERT INTO comunicaciones_transaccionales (
                        inscripcion_id,
                        clase_id,
                        tipo,
                        destinatario_email,
                        estado,
                        intentos
                    )
                    VALUES (
                        :inscripcion_id,
                        :clase_id,
                        'recordatorio_clase',
                        :destinatario_email,
                        'pendiente',
                        0
                    )
                ");

                $stmtInsert->execute([
                    ':inscripcion_id' =>
                        $destinatario['inscripcion_id'],

                    ':clase_id' =>
                        $clase['clase_id'],

                    ':destinatario_email' =>
                        $emailDestino
                ]);

                $comunicacionId = (int) $pdo->lastInsertId();

            } catch (PDOException $e) {

                /*
                * Segunda defensa frente a ejecuciones
                * simultáneas.
                */
                if ((string) $e->getCode() === '23000') {

                    echo
                        "  · OMITIDO: comunicación ya registrada " .
                        "para inscripción " .
                        $destinatario['inscripcion_id'] .
                        "\n";

                    continue;
                }

                throw $e;
            }
        }

        /*
         * Intentamos el envío.
         */
        try {

            $nombreCompleto = trim(
                $destinatario['nombre'] .
                ' ' .
                $destinatario['apellido']
            );

            $mailService->enviarRecordatorioClase(
                $emailDestino,
                $nombreCompleto,
                $clase['actividad_titulo'],
                $clase['clase_fecha_inicio'],
                $clase['modalidad'],
                $clase['zoom_url'] ?? '',
                $clase['zoom_meeting_id'] ?? '',
                $clase['zoom_passcode'] ?? '',
                (int) $clase['clase_numero'],
                (string) ($clase['clase_titulo'] ?? ''),
                (int) $clase['total_clases']
            );

            $stmtOk = $pdo->prepare("
                UPDATE comunicaciones_transaccionales
                SET
                    estado = 'enviado',
                    intentos = intentos + 1,
                    enviado_en = NOW(),
                    ultimo_intento_en = NOW(),
                    error_detalle = NULL
                WHERE id = :id
            ");

            $stmtOk->execute([
                ':id' => $comunicacionId
            ]);

            $enviadosClase++;
            $totalEnviados++;

            echo
                "  · ENVIADO: inscripción " .
                $destinatario['inscripcion_id'] .
                " → {$emailDestino}\n";

        } catch (Throwable $e) {

            /*
             * El registro queda guardado como error.
             * Nunca fingimos que el email salió.
             */
            $stmtError = $pdo->prepare("
                UPDATE comunicaciones_transaccionales
                SET
                    estado = 'error',
                    intentos = intentos + 1,
                    ultimo_intento_en = NOW(),
                    error_detalle = :error
                WHERE id = :id
            ");

            $stmtError->execute([
                ':error' => mb_substr(
                    $e->getMessage(),
                    0,
                    65000
                ),
                ':id' => $comunicacionId
            ]);

            $erroresClase++;
            $totalErrores++;

            echo
                "  · ERROR: inscripción " .
                $destinatario['inscripcion_id'] .
                " → " .
                $e->getMessage() .
                "\n";
        }
    }

    echo "Inscriptos confirmados: "
        . count($destinatarios)
        . "\n";

    echo "Recordatorios pendientes al iniciar: "
        . $pendientesClase
        . "\n";

    echo "Ya registrados al iniciar: "
        . $registradosClase
        . "\n";

    if ($esTest || $esRun) {

        echo "Enviados en esta ejecución: "
            . $enviadosClase
            . "\n";

        echo "Errores en esta ejecución: "
            . $erroresClase
            . "\n";
    }

    echo "----------------------------------------\n\n";
}

echo "========================================\n";
echo "RESUMEN\n";
echo "========================================\n";
echo "Clases: " . count($clases) . "\n";
echo "Destinatarios: {$totalDestinatarios}\n";
echo "Pendientes al iniciar: {$totalPendientes}\n";
echo "Ya registrados al iniciar: {$totalYaRegistrados}\n";
echo "Emails enviados: {$totalEnviados}\n";
echo "Errores: {$totalErrores}\n";
echo "========================================\n\n";