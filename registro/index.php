<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';


/* =========================================================
   HELPERS
   ========================================================= */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DATOS DE ENTRADA
   ========================================================= */

$dni = preg_replace(
    '/\D/',
    '',
    (string) ($_GET['dni'] ?? '')
);


if ($dni !== '') {

    if (strlen($dni) < 7 || strlen($dni) > 8) {
        http_response_code(422);
        exit('DNI inválido.');
    }


    /*
     * Seguridad:
     * el formulario de alta sólo debe mostrarse
     * si el DNI todavía no existe.
     */

    $stmt = $pdo->prepare("
        SELECT id
        FROM personas
        WHERE dni = :dni
        LIMIT 1
    ");

    $stmt->execute([
        'dni' => $dni
    ]);

    if ($stmt->fetch()) {
        header('Location: ./');
        exit;
    }
}


/* =========================================================
   CATÁLOGO DE ÁMBITOS
   ========================================================= */

$stmt = $pdo->query("
    SELECT
        id,
        nombre
    FROM ambitos_profesionales
    WHERE activo = 1
    ORDER BY id ASC
");

$ambitos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sumate a ECAP</title>

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@500;600&display=swap"
        rel="stylesheet"
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

        <a
            href="../"
            class="brand"
            aria-label="ECAP"
        >

            <img
                src="../assets/img/logoaz.png"
                alt="ECAP"
            >

        </a>

    </div>

</header>


<main class="registration-page">

    <div class="container">


        <?php if ($dni === ''): ?>


            <!-- =====================================================
                 PASO 1 · IDENTIFICACIÓN
                 ===================================================== -->

            <section class="registration-identification">

                <a
                    href="../"
                    class="registration-back"
                >
                    <span aria-hidden="true">←</span>
                    Volver
                </a>


                <div class="registration-identification-content">

                    <h1>Sumate a ECAP</h1>

                    <p>
                        Ingresá tu DNI para comenzar tu registro.
                    </p>


                    <form
                        id="dni-start-form"
                        class="verification-form"
                        novalidate
                    >

                        <div class="form-field">

                            <input
                                type="text"
                                id="dni-start"
                                inputmode="numeric"
                                autocomplete="off"
                                maxlength="8"
                                placeholder="Ej. 30123456"
                                required
                                autofocus
                            >

                            <span
                                class="field-error"
                                id="dni-start-error"
                            ></span>

                        </div>


                        <div class="registration-actions">

                            <button
                                type="submit"
                                class="form-submit"
                                id="dni-submit"
                            >
                                Continuar
                            </button>

                        </div>

                    </form>


                    <!-- PERSONA YA REGISTRADA -->

                    <div
                        id="already-registered"
                        class="existing-person"
                        hidden
                    >

                        <h2>
                            Ya sos parte de ECAP
                        </h2>

                        <p>
                            Tu DNI ya se encuentra registrado
                            en nuestra comunidad.
                        </p>

                        <a
                            href="../"
                            class="form-submit inline-submit"
                        >
                            Volver a ECAP
                        </a>

                        <button
                            type="button"
                            class="secondary-text-action"
                            id="other-dni-button"
                        >
                            Usar otro DNI
                        </button>

                    </div>

                </div>

            </section>


        <?php else: ?>


            <!-- =====================================================
                 PASO 2 · PERFIL
                 ===================================================== -->

            <section class="registration-form-section">


                <div class="registration-form-top">

                    <a
                        href="./"
                        class="registration-back"
                    >
                        <span aria-hidden="true">←</span>
                        Usar otro DNI
                    </a>

                </div>


                <div class="registration-form-heading">

                    <h1>
                        Completá tu perfil ECAP
                    </h1>

                    <p>
                        Estos datos formarán parte de tu perfil
                        para futuras actividades y propuestas
                        de capacitación.
                    </p>

                </div>


                <form
                    id="registration-form"
                    action="confirmar.php"
                    method="post"
                    novalidate
                >


                    <div class="form-grid">


                        <div class="form-field">

                            <label for="nombre">
                                Nombre
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                autocomplete="given-name"
                                required
                            >

                        </div>


                        <div class="form-field">

                            <label for="apellido">
                                Apellido
                            </label>

                            <input
                                type="text"
                                id="apellido"
                                name="apellido"
                                autocomplete="family-name"
                                required
                            >

                        </div>


                        <div class="form-field">

                            <label for="dni">
                                DNI
                            </label>

                            <input
                                type="text"
                                id="dni"
                                name="dni"
                                value="<?= e($dni) ?>"
                                class="readonly-input"
                                readonly
                                required
                            >

                        </div>


                        <div class="form-field">

                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                autocomplete="email"
                                required
                            >

                        </div>


                        <div class="form-field">

                            <label for="telefono">
                                Teléfono
                            </label>

                            <input
                                type="tel"
                                id="telefono"
                                name="telefono"
                                autocomplete="tel"
                                inputmode="numeric"
                                placeholder="Ej. 11 3200 8363"
                                required
                            >

                            <small class="field-help">
                                Ingresá código de área + número,
                                sin 0, sin 15 y sin +54.
                            </small>

                        </div>


                        <div class="form-field">

                            <label for="universidad-search">
                                Universidad de graduación
                            </label>

                            <div
                                class="search-select"
                                id="universidad-select"
                            >

                                <div class="search-input-wrap">

                                    <svg
                                        class="search-input-icon"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <circle
                                            cx="11"
                                            cy="11"
                                            r="6.5"
                                        ></circle>

                                        <path
                                            d="M16 16L21 21"
                                        ></path>
                                    </svg>

                                    <input
                                        type="text"
                                        id="universidad-search"
                                        placeholder="Buscar universidad..."
                                        autocomplete="off"
                                        required
                                    >

                                </div>


                                <input
                                    type="hidden"
                                    id="universidad_id"
                                    name="universidad_id"
                                >


                                <div
                                    class="search-results"
                                    id="universidad-results"
                                    hidden
                                ></div>

                            </div>

                            <small class="field-help">
                                Escribí para buscar y seleccioná
                                una universidad del listado.
                            </small>

                        </div>


                    </div>


                    <!-- ==================================================
                         ÁMBITOS
                         ================================================== -->

                    <fieldset class="professional-fields">

                        <legend>
                            ¿Dónde desempeñás tu actividad?
                        </legend>

                        <p>
                            Podés seleccionar más de una opción.
                        </p>


                        <div class="professional-options">

                            <?php foreach ($ambitos as $ambito): ?>

                                <label class="professional-option">

                                    <input
                                        type="checkbox"
                                        name="ambitos[]"
                                        value="<?= (int) $ambito['id'] ?>"
                                    >

                                    <span>
                                        <?= e((string) $ambito['nombre']) ?>
                                    </span>

                                </label>

                            <?php endforeach; ?>

                        </div>

                    </fieldset>


                    <!-- ==================================================
                         CONSENTIMIENTOS
                         ================================================== -->

                    <div class="consent-area">

                        <label class="check-field">

                            <input
                                type="checkbox"
                                name="acepta_datos"
                                value="1"
                                required
                            >

                            <span>
                                Acepto el tratamiento de mis datos
                                para gestionar mi perfil ECAP.
                            </span>

                        </label>


                        <label class="check-field">

                            <input
                                type="checkbox"
                                name="acepta_novedades"
                                value="1"
                            >

                            <span>
                                Quiero recibir información sobre
                                actividades, cursos y novedades de ECAP.
                            </span>

                        </label>

                    </div>


                    <div class="registration-actions">

                        <button
                            type="submit"
                            class="form-submit"
                        >
                            Crear mi perfil
                        </button>

                    </div>


                    <div
                        class="form-global-error"
                        id="form-global-error"
                        aria-live="polite"
                    ></div>


                </form>

            </section>


        <?php endif; ?>


    </div>

</main>


<?php if ($dni === ''): ?>

<script>

const dniStartForm =
    document.getElementById('dni-start-form');

const dniStart =
    document.getElementById('dni-start');

const dniStartError =
    document.getElementById('dni-start-error');

const dniSubmit =
    document.getElementById('dni-submit');

const alreadyRegistered =
    document.getElementById('already-registered');

const otherDniButton =
    document.getElementById('other-dni-button');


dniStart.addEventListener('input', () => {

    dniStart.value =
        dniStart.value.replace(/\D/g, '');

    dniStartError.textContent = '';

});


dniStartForm.addEventListener(
    'submit',
    async event => {

        event.preventDefault();

        const dni = dniStart.value.trim();


        if (
            dni.length < 7 ||
            dni.length > 8
        ) {

            dniStartError.textContent =
                'Ingresá un DNI válido.';

            dniStart.focus();

            return;
        }


        dniStartError.textContent = '';

        dniSubmit.disabled = true;
        dniSubmit.textContent = 'Verificando...';


        try {

            const response = await fetch(
                '../api/verificar-persona.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json'
                    },

                    body: JSON.stringify({
                        dni: dni,
                        contexto: 'registro'
                    })
                }
            );


            const data = await response.json();


            if (!response.ok || !data.ok) {

                throw new Error(
                    data.message ||
                    'No pudimos verificar el DNI.'
                );
            }


            if (data.estado === 'nueva') {

                window.location.href =
                    '?dni=' +
                    encodeURIComponent(dni);

                return;
            }


            if (data.estado === 'ya_registrado') {

                dniStartForm.hidden = true;
                alreadyRegistered.hidden = false;

                return;
            }


            throw new Error(
                'No pudimos continuar con el registro.'
            );


        } catch (error) {

            dniStartError.textContent =
                error.message;

        } finally {

            dniSubmit.disabled = false;
            dniSubmit.textContent = 'Continuar';

        }

    }
);


otherDniButton.addEventListener(
    'click',
    () => {

        alreadyRegistered.hidden = true;
        dniStartForm.hidden = false;

        dniStart.value = '';
        dniStartError.textContent = '';

        dniStart.focus();

    }
);

</script>


<?php else: ?>


<script>

/* =========================================================
   UNIVERSIDAD SEARCH
   ========================================================= */

const universitySearch =
    document.getElementById('universidad-search');

const universityId =
    document.getElementById('universidad_id');

const universityResults =
    document.getElementById('universidad-results');

const universitySelect =
    document.getElementById('universidad-select');

let searchTimer = null;


universitySearch.addEventListener(
    'input',
    function () {

        const query = this.value.trim();

        universityId.value = '';

        clearTimeout(searchTimer);


        if (query.length < 2) {

            universityResults.hidden = true;
            universityResults.innerHTML = '';

            return;
        }


        searchTimer = setTimeout(
            async () => {

                try {

                    const response = await fetch(
                        '../api/universidades.php?q=' +
                        encodeURIComponent(query)
                    );

                    const data = await response.json();

                    universityResults.innerHTML = '';


                    if (
                        !data.ok ||
                        !Array.isArray(data.universidades) ||
                        data.universidades.length === 0
                    ) {

                        const empty =
                            document.createElement('div');

                        empty.className =
                            'search-empty';

                        empty.textContent =
                            'No encontramos esa universidad.';

                        universityResults.appendChild(empty);

                        universityResults.hidden = false;

                        return;
                    }


                    data.universidades.forEach(
                        universidad => {

                            const button =
                                document.createElement('button');

                            button.type = 'button';

                            button.className =
                                'search-result-item';

                            button.textContent =
                                universidad.nombre;


                            button.addEventListener(
                                'click',
                                () => {

                                    universitySearch.value =
                                        universidad.nombre;

                                    universityId.value =
                                        universidad.id;

                                    universityResults.hidden = true;
                                    universityResults.innerHTML = '';

                                }
                            );


                            universityResults.appendChild(button);

                        }
                    );


                    universityResults.hidden = false;


                } catch (error) {

                    console.error(
                        'Error buscando universidades:',
                        error
                    );

                    universityResults.hidden = true;

                }

            },
            250
        );

    }
);


universitySearch.addEventListener(
    'blur',
    () => {

        setTimeout(() => {

            if (!universityId.value) {

                universitySearch.value = '';
                universityResults.hidden = true;
                universityResults.innerHTML = '';

            }

        }, 150);

    }
);


document.addEventListener(
    'click',
    event => {

        if (!universitySelect.contains(event.target)) {
            universityResults.hidden = true;
        }

    }
);

</script>


<?php endif; ?>


</body>

</html>