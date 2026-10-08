<?php

declare(strict_types=1);

?>
<aside
    class="admin-sidebar"
    id="admin-sidebar"
>

    <!-- IDENTIDAD INSTITUCIONAL -->

    <div class="admin-sidebar__brand">
        <a href="/admin/" aria-label="ECAP - Panel de Administración">
            <img
                src="/assets/img/logobc.png"
                alt="ECAP"
                class="admin-sidebar__logo"
            >
        </a>
    </div>


    <!-- NAVEGACIÓN ADMINISTRATIVA -->

    <nav
        class="admin-nav"
        aria-label="Navegación administrativa"
    >

        <!-- INICIO -->

        <div class="admin-nav__group">

            <span class="admin-nav__heading">
                Inicio
            </span>

            <a
                href="/admin/"
                class="admin-nav__item <?= $pageSection === 'inicio' ? 'is-active' : '' ?>"
                <?= $pageSection === 'inicio' ? 'aria-current="page"' : '' ?>
            >
                Panel general
            </a>

        </div>


        <!-- GESTIÓN ACADÉMICA -->

        <div class="admin-nav__group">

            <span class="admin-nav__heading">
                Gestión académica
            </span>

            <a
                href="/admin/actividades/"
                class="admin-nav__item <?= $pageSection === 'actividades' ? 'is-active' : '' ?>"
                <?= $pageSection === 'actividades' ? 'aria-current="page"' : '' ?>
            >
                Capacitaciones
            </a>

            <span class="admin-nav__item is-disabled">
                Clases
            </span>

            <a
                href="/admin/alumnos/"
                class="admin-nav__item <?= $pageSection === 'alumnos' ? 'is-active' : '' ?>"
                <?= $pageSection === 'alumnos' ? 'aria-current="page"' : '' ?>
            >
                Alumnos
            </a>

            <a
                href="/admin/docentes/"
                class="admin-nav__item <?= $pageSection === 'docentes' ? 'is-active' : '' ?>"
                <?= $pageSection === 'docentes' ? 'aria-current="page"' : '' ?>
            >
                Docentes
            </a>

            <span class="admin-nav__item is-disabled">
                Materiales
            </span>

            <span class="admin-nav__item is-disabled">
                Certificados
            </span>

        </div>


        <!-- GESTIÓN ECONÓMICA -->

        <div class="admin-nav__group">

            <span class="admin-nav__heading">
                Gestión económica
            </span>

            <span class="admin-nav__item is-disabled">
                Pagos
            </span>

            <span class="admin-nav__item is-disabled">
                Aranceles
            </span>

            <span class="admin-nav__item is-disabled">
                Descuentos y beneficios
            </span>

            <span class="admin-nav__item is-disabled">
                Convenios
            </span>

        </div>


        <!-- COMUNIDAD -->

        <div class="admin-nav__group">

            <span class="admin-nav__heading">
                Comunidad
            </span>

            <span class="admin-nav__item is-disabled">
                Personas
            </span>

        </div>


        <!-- COMUNICACIONES -->

        <div class="admin-nav__group">

            <span class="admin-nav__heading">
                Comunicaciones
            </span>

            <span class="admin-nav__item is-disabled">
                Correos transaccionales
            </span>

            <span class="admin-nav__item is-disabled">
                Recordatorios
            </span>

            <span class="admin-nav__item is-disabled">
                Historial de envíos
            </span>

        </div>


        <!-- ADMINISTRACIÓN -->

        <div class="admin-nav__group">

            <span class="admin-nav__heading">
                Administración
            </span>

            <span class="admin-nav__item is-disabled">
                Usuarios y permisos
            </span>

            <span class="admin-nav__item is-disabled">
                Configuración
            </span>

        </div>

    </nav>


    <!-- USUARIO Y SESIÓN -->

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


<!-- OVERLAY PARA NAVEGACIÓN MÓVIL -->

<div
    class="admin-sidebar-overlay"
    data-sidebar-close
></div>