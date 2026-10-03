<?php

declare(strict_types=1);

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Bienvenido a ECAP</title>

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <link
        rel="stylesheet"
        href="../assets/css/styles.css?v=<?= filemtime(
            __DIR__ . '/../assets/css/styles.css'
        ) ?>"
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

            <p class="success-eyebrow">
                Te damos la bienvenida a ECAP
            </p>

            <h1>
                Tu perfil fue creado
            </h1>

            <p class="success-text">
                Ya formás parte de la comunidad ECAP.
            </p>

            <p class="success-secondary">
                Desde ahora vas a poder utilizar tu perfil
                para participar de nuestras actividades
                y propuestas de capacitación.
            </p>

            <a
                href="../"
                class="form-submit success-action"
            >
                Volver a ECAP
            </a>

        </div>

    </div>

</main>


</body>

</html>