<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/services/MailService.php';


/* =========================================================
   SOLO POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}


/* =========================================================
   DATOS
   ========================================================= */

$edicionId = (int) ($_POST['edicion_id'] ?? 0);

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$apellido = trim((string) ($_POST['apellido'] ?? ''));

$dni = preg_replace(
    '/\D/',
    '',
    (string) ($_POST['dni'] ?? '')
);

$email = strtolower(
    trim((string) ($_POST['email'] ?? ''))
);

$telefono = trim(
    (string) ($_POST['telefono'] ?? '')
);

$universidadId = (int) (
    $_POST['universidad_id'] ?? 0
);

$universidadOtra = trim(
    (string) ($_POST['universidad_otra'] ?? '')
);

$ambitos = $_POST['ambitos'] ?? [];

$aceptaDatos =
    isset($_POST['acepta_datos']) &&
    $_POST['acepta_datos'] === '1';

$aceptaNovedades =
    isset($_POST['acepta_novedades']) &&
    $_POST['acepta_novedades'] === '1';


/* =========================================================
   VALIDACIONES BÁSICAS
   ========================================================= */

if ($edicionId <= 0) {
    http_response_code(422);
    exit('Edición inválida.');
}

if (
    $nombre === '' ||
    $apellido === '' ||
    strlen($dni) < 7 ||
    strlen($dni) > 8 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $telefono === ''
) {
    http_response_code(422);
    exit('Completá correctamente todos los datos obligatorios.');
}

if (!$aceptaDatos) {
    http_response_code(422);
    exit('Debés aceptar el tratamiento de datos para continuar.');
}

if (
    !is_array($ambitos) ||
    count($ambitos) === 0
) {
    http_response_code(422);
    exit('Seleccioná al menos un ámbito profesional.');
}


/* =========================================================
   VERIFICAR EDICIÓN
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM ediciones
    WHERE
        id = :id
        AND estado = 'inscripcion_abierta'
    LIMIT 1
");

$stmt->execute([
    'id' => $edicionId
]);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('La inscripción no está disponible.');
}


/* =========================================================
   NORMALIZAR UNIVERSIDAD
   ========================================================= */

$universidadIdFinal = null;
$universidadOtraFinal = null;

if ($universidadId > 0) {

    $stmt = $pdo->prepare("
        SELECT id
        FROM universidades
        WHERE
            id = :id
            AND activa = 1
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $universidadId
    ]);

    if (!$stmt->fetch()) {
        http_response_code(422);
        exit('La universidad seleccionada no es válida.');
    }

    $universidadIdFinal = $universidadId;

} elseif ($universidadOtra !== '') {

    $universidadOtraFinal = $universidadOtra;

} else {

    http_response_code(422);
    exit('Indicá tu universidad de graduación.');
}


/* =========================================================
   NORMALIZAR ÁMBITOS
   ========================================================= */

$ambitos = array_values(
    array_unique(
        array_filter(
            array_map('intval', $ambitos),
            static fn (int $id): bool => $id > 0
        )
    )
);

if (count($ambitos) === 0) {
    http_response_code(422);
    exit('Seleccioná al menos un ámbito profesional.');
}


/* =========================================================
   TRANSACCIÓN
   ========================================================= */

try {

    $pdo->beginTransaction();


    /* =====================================================
       EVITAR PERSONA DUPLICADA
       ===================================================== */

    $stmt = $pdo->prepare("
        SELECT id
        FROM personas
        WHERE dni = :dni
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        'dni' => $dni
    ]);

    $personaExistente = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($personaExistente) {

        $pdo->rollBack();

        http_response_code(409);

        exit(
            'Este DNI ya se encuentra registrado. Volvé al inicio para verificar tu identidad.'
        );
    }


    /* =====================================================
       CREAR PERSONA
       ===================================================== */

    $stmt = $pdo->prepare("
        INSERT INTO personas (
            nombre,
            apellido,
            dni,
            email,
            telefono,
            universidad_id,
            universidad_otra,
            estado,
            creado_en,
            actualizado_en
        )
        VALUES (
            :nombre,
            :apellido,
            :dni,
            :email,
            :telefono,
            :universidad_id,
            :universidad_otra,
            'activo',
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'dni' => $dni,
        'email' => $email,
        'telefono' => $telefono,
        'universidad_id' => $universidadIdFinal,
        'universidad_otra' => $universidadOtraFinal
    ]);

    $personaId = (int) $pdo->lastInsertId();


    /* =====================================================
       VALIDAR Y GUARDAR ÁMBITOS
       ===================================================== */

    $placeholders = implode(
        ',',
        array_fill(0, count($ambitos), '?')
    );

    $stmt = $pdo->prepare("
        SELECT id
        FROM ambitos_profesionales
        WHERE
            activo = 1
            AND id IN ($placeholders)
    ");

    $stmt->execute($ambitos);

    $ambitosValidos = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    sort($ambitos);
    sort($ambitosValidos);

    if ($ambitos !== $ambitosValidos) {
        throw new RuntimeException(
            'Uno de los ámbitos seleccionados no es válido.'
        );
    }


    $stmtAmbito = $pdo->prepare("
        INSERT INTO persona_ambitos (
            persona_id,
            ambito_id
        )
        VALUES (
            :persona_id,
            :ambito_id
        )
    ");

    foreach ($ambitosValidos as $ambitoId) {

        $stmtAmbito->execute([
            'persona_id' => $personaId,
            'ambito_id' => $ambitoId
        ]);
    }


    /* =====================================================
       CONSENTIMIENTO EMAIL
       ===================================================== */

    $stmt = $pdo->prepare("
        INSERT INTO consentimientos (
            persona_id,
            tipo,
            aceptado,
            origen,
            registrado_en
        )
        VALUES (
            :persona_id,
            'email_marketing',
            :aceptado,
            :origen,
            NOW()
        )
    ");

    $stmt->execute([
        'persona_id' => $personaId,
        'aceptado' => $aceptaNovedades ? 1 : 0,
        'origen' => 'inscripcion_ecap'
    ]);


    /* =====================================================
       CREAR INSCRIPCIÓN
       ===================================================== */

    $stmt = $pdo->prepare("
        INSERT INTO inscripciones (
            persona_id,
            edicion_id,
            estado,
            inscripto_en,
            cancelado_en
        )
        VALUES (
            :persona_id,
            :edicion_id,
            'confirmada',
            NOW(),
            NULL
        )
    ");

    $stmt->execute([
        'persona_id' => $personaId,
        'edicion_id' => $edicionId
    ]);


    /* =====================================================
       FINALIZAR
       ===================================================== */

    $pdo->commit();


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Error confirmando inscripción ECAP: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'No pudimos completar la inscripción. Intentá nuevamente.'
    );
}

/* =========================================================
   EMAIL DE CONFIRMACIÓN
   ========================================================= */

try {

    $stmt = $pdo->prepare("
        SELECT
            a.titulo,
            e.fecha_inicio,
            e.modalidad,
            e.zoom_url,
            e.zoom_meeting_id,
            e.zoom_passcode
        FROM ediciones e
        INNER JOIN actividades a
            ON a.id = e.actividad_id
        WHERE e.id = :edicion_id
        LIMIT 1
    ");

    $stmt->execute([
        'edicion_id' => $edicionId
    ]);

    $datosActividad = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$datosActividad) {
        throw new RuntimeException(
            'No se encontraron los datos de la actividad.'
        );
    }


    $mailService = new MailService();

    $mailService->enviarConfirmacionInscripcion(
        $email,
        $nombre,
        (string) $datosActividad['titulo'],
        (string) $datosActividad['fecha_inicio'],
        (string) $datosActividad['modalidad'],
        (string) ($datosActividad['zoom_url'] ?? ''),
        (string) ($datosActividad['zoom_meeting_id'] ?? ''),
        (string) ($datosActividad['zoom_passcode'] ?? '')
    );


} catch (Throwable $e) {

    /*
     * La inscripción ya quedó confirmada.
     * Si falla el email, no revertimos la inscripción.
     */

    error_log(
        'Error enviando confirmación ECAP: ' .
        $e->getMessage()
    );
}

/* =========================================================
   REDIRECCIÓN FINAL
   ========================================================= */

header(
    'Location: ./exito.php?edicion=' .
    urlencode((string) $edicionId)
);

exit;