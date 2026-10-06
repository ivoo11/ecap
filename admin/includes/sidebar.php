<?php

declare(strict_types=1);

?>
<aside
    class="admin-sidebar"
    id="admin-sidebar"
>

    <div class="admin-sidebar__brand">
        <a href="/admin/" aria-label="ECAP - Panel de Administración">
            <img
                src="/assets/img/logobc.png"
                alt="ECAP"
                class="admin-sidebar__logo"
            >
        </a>
    </div>

    <nav
        class="admin-nav"
        aria-label="Navegación administrativa"
    >

        <a
            href="/admin/"
            class="admin-nav__item <?= $pageSection === 'inicio' ? 'is-active' : '' ?>"
        >
            Inicio
        </a>

        <a
            href="/admin/actividades/"
            class="admin-nav__item <?= $pageSection === 'actividades' ? 'is-active' : '' ?>"
        >
            Actividades
        </a>

        <a
            href="/admin/docentes/"
            class="admin-nav__item <?= $pageSection === 'docentes' ? 'is-active' : '' ?>"
        >
            Docentes
        </a>

        <a
            href="/admin/alumnos/"
            class="admin-nav__item <?= $pageSection === 'alumnos' ? 'is-active' : '' ?>"
        >
            Alumnos
        </a>

    </nav>

    <div class="admin-sidebar__footer">

        <div class="admin-sidebar__user">

            <strong>
                <?= htmlspecialchars(
                    $usuario['nombre'] . ' ' . $usuario['apellido'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <span>
                <?= Auth::hasRole('superadmin')
                    ? 'Superadministrador'
                    : 'Administrador' ?>
            </span>

        </div>

        <form
            method="post"
            action="/admin/logout.php"
        >
            <?= Csrf::field() ?>

            <button
                class="admin-logout"
                type="submit"
            >
                Cerrar sesión
            </button>
        </form>

    </div>

</aside>

<div
    class="admin-sidebar-overlay"
    data-sidebar-close
></div>