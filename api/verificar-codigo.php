<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/services/MailService.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Método no permitido.'
    ]);

    exit;
}


$input = json_decode(
    file_get_contents('php://input'),
    true
);


$verificacionId = (int) (
    $input['verificacion_id'] ?? 0
);

$codigo = preg_replace(
    '/\D/',
    '',
    (string) ($input['codigo'] ?? '')
);


if (
    $verificacionId <= 0 ||
    strlen($codigo) !== 6
) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'Código inválido.'
    ]);

    exit;
}


/* =========================================================
   BUSCAR VERIFICACIÓN
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        persona_id,
        edicion_id,
        codigo_hash,
        intentos,
        expira_en,
        verificado_en
    FROM verificaciones_email
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $verificacionId
]);

$verificacion = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$verificacion) {

    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'message' => 'La verificación no existe.'
    ]);

    exit;
}


/* =========================================================
   YA UTILIZADO
   ========================================================= */

if ($verificacion['verificado_en'] !== null) {

    http_response_code(409);

    echo json_encode([
        'ok' => false,
        'message' => 'Este código ya fue utilizado.'
    ]);

    exit;
}


/* =========================================================
   EXPIRACIÓN
   ========================================================= */

if (
    strtotime($verificacion['expira_en']) < time()
) {

    http_response_code(410);

    echo json_encode([
        'ok' => false,
        'message' => 'El código venció. Solicitá uno nuevo.'
    ]);

    exit;
}


/* =========================================================
   INTENTOS
   ========================================================= */

if ((int) $verificacion['intentos'] >= 5) {

    http_response_code(429);

    echo json_encode([
        'ok' => false,
        'message' => 'Superaste la cantidad de intentos.'
    ]);

    exit;
}


/* =========================================================
   VALIDAR CÓDIGO
   ========================================================= */

if (
    !password_verify(
        $codigo,
        $verificacion['codigo_hash']
    )
) {

    $stmt = $pdo->prepare("
        UPDATE verificaciones_email
        SET intentos = intentos + 1
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $verificacionId
    ]);


    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'El código ingresado no es correcto.'
    ]);

    exit;
}

/* =========================================================
   VERIFICACIÓN CORRECTA + INSCRIPCIÓN
   ========================================================= */

try {

    $pdo->beginTransaction();

    // Serializar la admisión con los otros caminos que bloquean la edición.
    $stmtEdicion = $pdo->prepare("
        SELECT id, estado, cupo_maximo, fecha_inicio
        FROM ediciones WHERE id = ? FOR UPDATE
    ");
    $stmtEdicion->execute([$verificacion['edicion_id']]);
    $edicionActual = $stmtEdicion->fetch(PDO::FETCH_ASSOC);
    if (!$edicionActual || $edicionActual['estado'] !== 'inscripcion_abierta'
        || strtotime($edicionActual['fecha_inicio']) <= time()) {
        throw new DomainException('La inscripción ya no está disponible.');
    }
    $stmtPersona = $pdo->prepare('SELECT estado FROM personas WHERE id = ?');
    $stmtPersona->execute([$verificacion['persona_id']]);
    if ($stmtPersona->fetchColumn() !== 'activo') {
        throw new DomainException('El perfil no está activo.');
    }



    /* =====================================================
       MARCAR VERIFICACIÓN COMO UTILIZADA
       ===================================================== */

    $stmt = $pdo->prepare("
        UPDATE verificaciones_email
        SET verificado_en = NOW()
        WHERE
            id = :id
            AND verificado_en IS NULL
          AND expira_en >= NOW()
          AND intentos < 5
    ");

    $stmt->execute([
        'id' => $verificacionId
    ]);


    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'La verificación ya fue utilizada.'
        );
    }


    /* =====================================================
       COMPROBAR SI YA EXISTE INSCRIPCIÓN
       ===================================================== */

    $stmt = $pdo->prepare("
        SELECT
            id,
            estado
        FROM inscripciones
        WHERE
            persona_id = :persona_id
            AND edicion_id = :edicion_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        'persona_id' => $verificacion['persona_id'],
        'edicion_id' => $verificacion['edicion_id']
    ]);

    $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((!$inscripcion || $inscripcion['estado'] !== 'confirmada')
        && $edicionActual['cupo_maximo'] !== null) {
        $stmtCupo = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE edicion_id = ? AND estado = 'confirmada'");
        $stmtCupo->execute([$verificacion['edicion_id']]);
        if ((int) $stmtCupo->fetchColumn() >= (int) $edicionActual['cupo_maximo']) {
            throw new DomainException('La actividad alcanzó el cupo máximo.');
        }
    }



    /* =====================================================
       CREAR / RECUPERAR INSCRIPCIÓN
       ===================================================== */

    if (!$inscripcion) {

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
            'persona_id' => $verificacion['persona_id'],
            'edicion_id' => $verificacion['edicion_id']
        ]);

    } elseif ($inscripcion['estado'] !== 'confirmada') {

        $stmt = $pdo->prepare("
            UPDATE inscripciones
            SET
                estado = 'confirmada',
                inscripto_en = NOW(),
                cancelado_en = NULL
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $inscripcion['id']
        ]);
    }


    $pdo->commit();


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof DomainException) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }

    error_log(
        'Error verificando e inscribiendo en ECAP: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'No pudimos completar la inscripción.'
    ]);

    exit;
}

/* =========================================================
   EMAIL DE CONFIRMACIÓN
   ========================================================= */

try {
    require_once __DIR__.'/../app/services/InscripcionService.php';
    (new InscripcionService($pdo))->enviarConfirmacion((int)$verificacion['persona_id'],(int)$verificacion['edicion_id']);

} catch (Throwable $e) {

    /*
     * La inscripción YA fue confirmada.
     * Un fallo del email no debe romper la inscripción.
     */

    error_log(
        'Error enviando confirmación ECAP: ' .
        $e->getMessage()
    );
}

/* =========================================================
   RESPUESTA
   ========================================================= */

echo json_encode([
    'ok' => true,
    'estado' => 'inscripto',
    'edicion_id' => (int) $verificacion['edicion_id']
]);