<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Administración';
$pageSection = $pageSection ?? '';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | ECAP
    </title>

    <link
        rel="stylesheet"
        href="/assets/admin/css/admin.css?v=<?= filemtime(
            __DIR__ . '/../../assets/admin/css/admin.css'
        ) ?>"
    >
</head>

<body>

<div class="admin-shell">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="admin-main">

        <header class="admin-topbar">

            <button
                class="admin-menu-toggle"
                type="button"
                aria-label="Abrir menú"
                aria-expanded="false"
                aria-controls="admin-sidebar"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="admin-topbar__context">
                <?= htmlspecialchars(
                    $pageTitle,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <div class="admin-topbar__identity">
                PANEL DE ADMINISTRACIÓN
            </div>

        </header>

        <main class="admin-content">