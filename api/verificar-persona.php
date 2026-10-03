<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/config/database.php';


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


$dni = preg_replace(
    '/\D/',
    '',
    (string) ($input['dni'] ?? '')
);

$edicionId = (int) ($input['edicion_id'] ?? 0);
$contexto = (string) ($input['contexto'] ?? 'inscripcion');


if (
    strlen($dni) < 7 ||
    strlen($dni) > 8
) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'DNI inválido.'
    ]);

    exit;
}


if (
    !in_array(
        $contexto,
        ['inscripcion', 'registro'],
        true
    )
) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'Contexto inválido.'
    ]);

    exit;
}


if (
    $contexto === 'inscripcion' &&
    $edicionId <= 0
) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'Edición inválida.'
    ]);

    exit;
}


/* =========================================================
   VERIFICAR EDICIÓN
   ========================================================= */

if ($contexto === 'inscripcion') {

    $stmtEdicion = $pdo->prepare("
        SELECT id
        FROM ediciones
        WHERE
            id = :id
            AND estado = 'inscripcion_abierta'
        LIMIT 1
    ");

    $stmtEdicion->execute([
        'id' => $edicionId
    ]);


    if (!$stmtEdicion->fetch()) {

        http_response_code(404);

        echo json_encode([
            'ok' => false,
            'message' => 'La inscripción no está disponible.'
        ]);

        exit;
    }
}

/* =========================================================
   BUSCAR PERSONA
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        email
    FROM personas
    WHERE
        dni = :dni
        AND estado = 'activo'
    LIMIT 1
");

$stmt->execute([
    'dni' => $dni
]);

$persona = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================================================
   PERSONA NUEVA
   ========================================================= */

if (!$persona) {

    echo json_encode([
        'ok' => true,
        'existe' => false,
        'estado' => 'nueva'
    ]);

    exit;
}

/* =========================================================
   REGISTRO GENERAL · PERSONA YA EXISTENTE
   ========================================================= */

if ($contexto === 'registro') {

    echo json_encode([
        'ok' => true,
        'existe' => true,
        'estado' => 'ya_registrado'
    ]);

    exit;
}

/* =========================================================
   VERIFICAR SI YA ESTÁ INSCRIPTO
   ========================================================= */

$stmtInscripcion = $pdo->prepare("
    SELECT id
    FROM inscripciones
    WHERE
        persona_id = :persona_id
        AND edicion_id = :edicion_id
        AND estado = 'confirmada'
    LIMIT 1
");

$stmtInscripcion->execute([
    'persona_id' => $persona['id'],
    'edicion_id' => $edicionId
]);


if ($stmtInscripcion->fetch()) {

    echo json_encode([
        'ok' => true,
        'existe' => true,
        'estado' => 'ya_inscripto'
    ]);

    exit;
}


/* =========================================================
   EMAIL ENMASCARADO
   ========================================================= */

$email = (string) $persona['email'];

[$usuario, $dominio] = array_pad(
    explode('@', $email, 2),
    2,
    ''
);

$primerCaracter = mb_substr(
    $usuario,
    0,
    1
);

$emailEnmascarado =
    $primerCaracter .
    '*****@' .
    $dominio;


/* =========================================================
   REQUIERE VERIFICACIÓN
   ========================================================= */

echo json_encode([
    'ok' => true,
    'existe' => true,
    'estado' => 'requiere_verificacion',
    'email_enmascarado' => $emailEnmascarado
]);