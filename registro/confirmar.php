<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';


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

$nombre = mb_convert_case(
    mb_strtolower(
        trim((string) ($_POST['nombre'] ?? '')),
        'UTF-8'
    ),
    MB_CASE_TITLE,
    'UTF-8'
);

$apellido = mb_convert_case(
    mb_strtolower(
        trim((string) ($_POST['apellido'] ?? '')),
        'UTF-8'
    ),
    MB_CASE_TITLE,
    'UTF-8'
);

$dni = preg_replace(
    '/\D/',
    '',
    (string) ($_POST['dni'] ?? '')
);

$email = strtolower(
    trim((string) ($_POST['email'] ?? ''))
);

$telefono = preg_replace(
    '/\D/',
    '',
    (string) ($_POST['telefono'] ?? '')
);


/*
 * Normalización de teléfonos argentinos.
 *
 * Guardamos:
 * código de área + número
 *
 * Ejemplo:
 * +54 11 15 3200-8363
 * 011 15 3200-8363
 * 11 3200 8363
 *
 * Todos deberían terminar almacenados
 * sin código de país ni 0 inicial.
 */

if (str_starts_with($telefono, '54')) {
    $telefono = substr($telefono, 2);
}

if (str_starts_with($telefono, '0')) {
    $telefono = substr($telefono, 1);
}


/*
 * No eliminamos "15" automáticamente.
 * Puede formar parte de un número válido.
 */


$universidadId = (int) (
    $_POST['universidad_id'] ?? 0
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

if (
    $nombre === '' ||
    $apellido === '' ||
    strlen($dni) < 7 ||
    strlen($dni) > 8 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($telefono) < 10 ||
    strlen($telefono) > 11
) {

    http_response_code(422);

    exit(
        'Completá correctamente todos los datos obligatorios.'
    );
}


if (!$aceptaDatos) {

    http_response_code(422);

    exit(
        'Debés aceptar el tratamiento de datos para continuar.'
    );
}


if (
    !is_array($ambitos) ||
    count($ambitos) === 0
) {

    http_response_code(422);

    exit(
        'Seleccioná al menos un ámbito profesional.'
    );
}


/* =========================================================
   VALIDAR UNIVERSIDAD
   ========================================================= */

if ($universidadId <= 0) {

    http_response_code(422);

    exit(
        'Seleccioná una universidad del listado.'
    );
}


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

    exit(
        'La universidad seleccionada no es válida.'
    );
}


$universidadIdFinal = $universidadId;
$universidadOtraFinal = null;


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

    exit(
        'Seleccioná al menos un ámbito profesional.'
    );
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

        header('Location: ./?estado=existente');
        exit;
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
       VALIDAR ÁMBITOS
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


    /* =====================================================
       GUARDAR ÁMBITOS
       ===================================================== */

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
        'origen' => 'registro_ecap'
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
        'Error creando perfil ECAP: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'No pudimos completar el registro. Intentá nuevamente.'
    );
}


/* =========================================================
   REDIRECCIÓN FINAL
   ========================================================= */

header('Location: ./exito.php');
exit;