<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';


/* =========================================================
   EDICIÓN
   ========================================================= */

$edicionId = (int) ($_GET['edicion'] ?? 0);

if ($edicionId <= 0) {
    http_response_code(400);
    exit('Edición inválida.');
}


$stmt = $pdo->prepare("
    SELECT
        e.id,
        a.titulo,
        a.slug
    FROM ediciones e
    INNER JOIN actividades a
        ON a.id = e.actividad_id
    WHERE e.id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $edicionId
]);

$edicion = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$edicion) {
    http_response_code(404);
    exit('Actividad no encontrada.');
}


$actividadUrl =
    '../actividades/?slug=' .
    rawurlencode((string) $edicion['slug']);

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Inscripción confirmada | ECAP</title>

    <link
        rel="stylesheet"
        href="../assets/css/styles.css"
    >

</head>

<body>

<header class="site-header">

    <div class="container header-inner">

        <a href="../" class="brand">

            <img
                src="../assets/img/logoaz.png"
                alt="ECAP"
            >

        </a>

    </div>

</header>


<main class="registration-page">

    <div class="container">

        <div class="registration-success">

            <span class="success-mark" aria-hidden="true">
                ✓
            </span>

            <p class="success-eyebrow">
                Te damos la bienvenida a la cursada
            </p>

            <h1>
                Inscripción confirmada
            </h1>

            <p class="success-text">
                Tu inscripción a
                <strong><?= htmlspecialchars(
                    (string) $edicion['titulo'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></strong>
                fue realizada con éxito.
            </p>

            <p class="success-secondary">
                Te enviaremos por email la información necesaria
                para participar de la actividad.
            </p>

            <a
                href="<?= htmlspecialchars(
                    $actividadUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="form-submit success-action"
            >
                Volver a la actividad
            </a>

        </div>

    </div>

</main>

</body>

</html>