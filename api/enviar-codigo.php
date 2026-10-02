<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfig = require __DIR__ . '/../app/config/mail.php';


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


if (
    strlen($dni) < 7 ||
    strlen($dni) > 8 ||
    $edicionId <= 0
) {
    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'Datos inválidos.'
    ]);

    exit;
}


/* =========================================================
   EDICIÓN
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

if (!$stmt->fetchColumn()) {
    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'message' => 'La inscripción no está disponible.'
    ]);

    exit;
}


/* =========================================================
   PERSONA
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


if (!$persona) {
    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'message' => 'No encontramos el perfil.'
    ]);

    exit;
}


/* =========================================================
   YA INSCRIPTO
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM inscripciones
    WHERE
        persona_id = :persona_id
        AND edicion_id = :edicion_id
        AND estado = 'confirmada'
    LIMIT 1
");

$stmt->execute([
    'persona_id' => $persona['id'],
    'edicion_id' => $edicionId
]);


if ($stmt->fetchColumn()) {
    echo json_encode([
        'ok' => false,
        'estado' => 'ya_inscripto',
        'message' => 'Ya estás inscripto en esta actividad.'
    ]);

    exit;
}


/* =========================================================
   GENERAR CÓDIGO
   ========================================================= */

$codigo = (string) random_int(100000, 999999);

$codigoHash = password_hash(
    $codigo,
    PASSWORD_DEFAULT
);

$expiraEn = date(
    'Y-m-d H:i:s',
    time() + 600
);


try {

    $pdo->beginTransaction();


    /*
     * Invalidamos verificaciones anteriores pendientes.
     */

    $stmt = $pdo->prepare("
        UPDATE verificaciones_email
        SET verificado_en = NOW()
        WHERE
            persona_id = :persona_id
            AND edicion_id = :edicion_id
            AND verificado_en IS NULL
    ");

    $stmt->execute([
        'persona_id' => $persona['id'],
        'edicion_id' => $edicionId
    ]);


    /*
     * Creamos la nueva verificación.
     */

    $stmt = $pdo->prepare("
        INSERT INTO verificaciones_email (
            persona_id,
            edicion_id,
            codigo_hash,
            intentos,
            expira_en
        )
        VALUES (
            :persona_id,
            :edicion_id,
            :codigo_hash,
            0,
            :expira_en
        )
    ");

    $stmt->execute([
        'persona_id' => $persona['id'],
        'edicion_id' => $edicionId,
        'codigo_hash' => $codigoHash,
        'expira_en' => $expiraEn
    ]);

    $verificacionId = (int) $pdo->lastInsertId();

    $pdo->commit();


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'No pudimos generar el código.'
    ]);

    exit;
}


/* =========================================================
   ENVIAR CÓDIGO POR EMAIL
   ========================================================= */

try {

    $mail = new PHPMailer(true);

    $mail->isSMTP();

    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;

    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) $mailConfig['port'];

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        $mailConfig['from_email'],
        $mailConfig['from_name']
    );

    $mail->addAddress(
        (string) $persona['email']
    );

    $mail->isHTML(true);

    $mail->Subject = 'Tu código de verificación | ECAP';

    $mail->Body = '
        <div style="
            max-width: 560px;
            margin: 0 auto;
            padding: 40px 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #101d30;
        ">

            <p style="
                margin: 0 0 24px;
                font-size: 14px;
                color: #6f747c;
            ">
                ECAP
            </p>

            <h1 style="
                margin: 0 0 16px;
                font-size: 28px;
                line-height: 1.15;
            ">
                Verificá tu identidad
            </h1>

            <p style="
                margin: 0 0 30px;
                font-size: 16px;
                line-height: 1.6;
                color: #555b64;
            ">
                Ingresá este código para continuar con tu inscripción.
            </p>

            <div style="
                margin: 0 0 30px;
                font-size: 36px;
                font-weight: 700;
                letter-spacing: 8px;
                color: #101d30;
            ">
                ' . htmlspecialchars(
                    $codigo,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '
            </div>

            <p style="
                margin: 0;
                font-size: 14px;
                line-height: 1.6;
                color: #777c84;
            ">
                El código vence en 10 minutos.
                Si no solicitaste este código, podés ignorar este mensaje.
            </p>

        </div>
    ';

    $mail->AltBody =
        "Tu código de verificación ECAP es: {$codigo}\n\n" .
        "El código vence en 10 minutos.";

    $mail->send();


} catch (Exception $e) {

    error_log(
        'Error enviando código ECAP: ' .
        $mail->ErrorInfo
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'No pudimos enviar el código por email.'
    ]);

    exit;
}


/* =========================================================
   RESPUESTA
   ========================================================= */

echo json_encode([
    'ok' => true,
    'estado' => 'codigo_enviado',
    'verificacion_id' => $verificacionId
]);