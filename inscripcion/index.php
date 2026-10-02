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

$edicionId = (int) ($_GET['edicion'] ?? $_POST['edicion_id'] ?? 0);

$dni = preg_replace(
    '/\D/',
    '',
    (string) ($_GET['dni'] ?? $_POST['dni'] ?? '')
);


if ($edicionId <= 0) {
    http_response_code(400);
    exit('Edición inválida.');
}


/* =========================================================
   EDICIÓN + ACTIVIDAD
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        e.id AS edicion_id,
        e.fecha_inicio,
        e.modalidad,
        e.estado,
        a.id AS actividad_id,
        a.titulo,
        a.slug
    FROM ediciones e
    INNER JOIN actividades a
        ON a.id = e.actividad_id
    WHERE e.id = :edicion_id
    LIMIT 1
");

$stmt->execute([
    'edicion_id' => $edicionId
]);

$edicion = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$edicion) {
    http_response_code(404);
    exit('Actividad no encontrada.');
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


/* =========================================================
   PERSONA
   ========================================================= */

$persona = null;
$ambitosPersona = [];

$nombre = '';
$apellido = '';
$email = '';
$telefono = '';

$universidadId = null;
$universidadNombre = '';
$universidadOtra = '';


if ($dni !== '') {

    if (strlen($dni) < 7 || strlen($dni) > 8) {
        http_response_code(422);
        exit('DNI inválido.');
    }


    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.nombre,
            p.apellido,
            p.dni,
            p.email,
            p.telefono,
            p.universidad_id,
            p.universidad_otra,
            u.nombre AS universidad_nombre
        FROM personas p
        LEFT JOIN universidades u
            ON u.id = p.universidad_id
        WHERE p.dni = :dni
        LIMIT 1
    ");

    $stmt->execute([
        'dni' => $dni
    ]);

    $persona = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;


    if ($persona) {

        $nombre = (string) $persona['nombre'];
        $apellido = (string) $persona['apellido'];
        $email = (string) $persona['email'];
        $telefono = (string) ($persona['telefono'] ?? '');

        $universidadId = $persona['universidad_id']
            ? (int) $persona['universidad_id']
            : null;

        $universidadNombre = (string) (
            $persona['universidad_nombre'] ?? ''
        );

        $universidadOtra = (string) (
            $persona['universidad_otra'] ?? ''
        );


        /* ÁMBITOS YA ASOCIADOS */

        $stmtAmbitos = $pdo->prepare("
            SELECT ambito_id
            FROM persona_ambitos
            WHERE persona_id = :persona_id
        ");

        $stmtAmbitos->execute([
            'persona_id' => $persona['id']
        ]);

        $ambitosPersona = array_map(
            'intval',
            $stmtAmbitos->fetchAll(PDO::FETCH_COLUMN)
        );
    }
}

/* =========================================================
   URL VOLVER
   ========================================================= */

$volverUrl =
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

    <title>Registro · ECAP</title>

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
        href="../assets/css/styles.css"
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

            <section class="registration-identification">

                <a
                    href="<?= e($volverUrl) ?>"
                    class="registration-back"
                >
                    <span aria-hidden="true">←</span>
                    Volver a la actividad
                </a>

                <div class="registration-identification-content">

                    <h1>Ingresá tu DNI</h1>

                    <p>
                        Lo utilizaremos para identificarte y continuar
                        con tu inscripción.
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


                    <!-- PERSONA EXISTENTE -->

                    <div
                        id="existing-person"
                        class="existing-person"
                        hidden
                    >

                        <h2>
                            Ya tenemos tus datos
                        </h2>

                        <p>
                            Para continuar, verificá tu identidad con el
                            email asociado a tu perfil ECAP.
                        </p>

                        <div class="existing-email">

                            <span>
                                Enviaremos un código a
                            </span>

                            <strong id="masked-email"></strong>

                        </div>

                        <button
                            type="button"
                            class="form-submit"
                            id="send-code-button"
                        >
                            Enviar código
                        </button>

                        <button
                            type="button"
                            class="secondary-text-action"
                            id="other-dni-button"
                        >
                            Usar otro DNI
                        </button>

                    </div>

                    <!-- VERIFICACIÓN POR CÓDIGO -->

                    <div
                        id="verification-step"
                        class="existing-person verification-step"
                        hidden
                    >
                        <h2>Revisá tu email</h2>

                        <p>
                            Ingresá el código de 6 dígitos que enviamos a
                            <strong id="verification-email"></strong>.
                        </p>

                        <form
                            id="verification-form"
                            class="verification-form"
                            novalidate
                        >
                            <div class="form-field">

                                <label for="verification-code">
                                    Código de verificación
                                </label>

                                <input
                                    type="text"
                                    id="verification-code"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="6"
                                    placeholder="000000"
                                    required
                                >

                                <span
                                    class="field-error"
                                    id="verification-error"
                                ></span>

                            </div>

                            <button
                                type="submit"
                                class="form-submit"
                                id="verification-submit"
                            >
                                Verificar y continuar
                            </button>

                        </form>

                        <button
                            type="button"
                            class="secondary-text-action"
                            id="verification-back"
                        >
                            ← Volver
                        </button>

                    </div>

                    <!-- YA INSCRIPTO -->

                    <div
                        id="already-registered"
                        class="existing-person"
                        hidden
                    >

                        <h2>
                            Inscripción Confirmada
                        </h2>

                        <p>
                            Tu inscripción a esta actividad fue realizada con éxito.
                        </p>

                        <a
                            href="<?= e($volverUrl) ?>"
                            class="form-submit inline-submit"
                        >
                            Volver a la actividad
                        </a>

                    </div>

                </div>

            </section>

            <?php else: ?>


            <!-- =====================================================
                 PASO 2 · DATOS
                 ===================================================== -->

            <section class="registration-form-section">


                <div class="registration-form-top">

                    <a
                        href="?edicion=<?= $edicionId ?>"
                        class="registration-back"
                    >
                        <span aria-hidden="true">←</span>
                        Usar otro DNI
                    </a>

                </div>


                <div class="registration-form-heading">

                    <h1>
                        Completá tus datos
                    </h1>

                    <p>
                        Esta información quedará asociada a tu perfil
                        ECAP para futuras actividades.
                    </p>

                </div>


                <form
                    id="registration-form"
                    action="confirmar.php"
                    method="post"
                    novalidate
                >

                    <input
                        type="hidden"
                        name="edicion_id"
                        value="<?= $edicionId ?>"
                    >


                    <!-- ==================================================
                         DATOS PERSONALES
                         ================================================== -->

                    <div class="form-grid">


                        <div class="form-field">

                            <label for="nombre">
                                Nombre
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                value="<?= e($nombre) ?>"
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
                                value="<?= e($apellido) ?>"
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
                                value="<?= e($email) ?>"
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
                                value="<?= e($telefono) ?>"
                                autocomplete="tel"
                                inputmode="numeric"
                                placeholder="Ej. 11 3200 8363"
                                required
                            >

                            <small class="field-help">
                                Ingresá código de área + número, sin 0, sin 15 y sin +54.
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
                                    <circle cx="11" cy="11" r="6.5"></circle>
                                    <path d="M16 16L21 21"></path>
                                </svg>

                                <input
                                    type="text"
                                    id="universidad-search"
                                    value="<?= e(
                                        $universidadNombre !== ''
                                            ? $universidadNombre
                                            : $universidadOtra
                                    ) ?>"
                                    placeholder="Ej. Universidad de Buenos Aires"
                                    autocomplete="off"
                                    required
                                >

                            </div>

                            <input
                                type="hidden"
                                id="universidad_id"
                                name="universidad_id"
                                value="<?= $universidadId ?? '' ?>"
                            >

                            <input
                                type="hidden"
                                id="universidad_otra"
                                name="universidad_otra"
                                value="<?= e($universidadOtra) ?>"
                            >

                            <div
                                class="search-results"
                                id="universidad-results"
                                hidden
                            ></div>

                        </div>

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

                                <?php

                                $ambitoId = (int) $ambito['id'];

                                $checked = in_array(
                                    $ambitoId,
                                    $ambitosPersona,
                                    true
                                );

                                ?>

                                <label class="professional-option">

                                    <input
                                        type="checkbox"
                                        name="ambitos[]"
                                        value="<?= $ambitoId ?>"
                                        <?= $checked ? 'checked' : '' ?>
                                    >

                                    <span>
                                        <?= e($ambito['nombre']) ?>
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
                                Acepto el tratamiento de mis datos para
                                gestionar mi inscripción.
                            </span>

                        </label>


                        <label class="check-field">

                            <input
                                type="checkbox"
                                name="acepta_novedades"
                                value="1"
                            >

                            <span>
                                Quiero recibir información sobre actividades,
                                cursos y novedades de ECAP.
                            </span>

                        </label>

                    </div>


                    <!-- ==================================================
                         SUBMIT
                         ================================================== -->

                    <div class="registration-actions">

                        <button
                            type="submit"
                            class="form-submit"
                        >
                            Confirmar inscripción
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

/* =========================================================
   PASO DNI
   ========================================================= */

const dniStartForm =
    document.getElementById('dni-start-form');

const dniStart =
    document.getElementById('dni-start');

const dniStartError =
    document.getElementById('dni-start-error');

const dniSubmit =
    document.getElementById('dni-submit');

const existingPerson =
    document.getElementById('existing-person');

const verificationStep =
    document.getElementById('verification-step');

const verificationForm =
    document.getElementById('verification-form');

const verificationCode =
    document.getElementById('verification-code');

const verificationError =
    document.getElementById('verification-error');

const verificationSubmit =
    document.getElementById('verification-submit');

const verificationEmail =
    document.getElementById('verification-email');

const verificationBack =
    document.getElementById('verification-back');


let currentVerificationId = null;
let currentMaskedEmail = '';

const alreadyRegistered =
    document.getElementById('already-registered');

const maskedEmail =
    document.getElementById('masked-email');

const sendCodeButton =
    document.getElementById('send-code-button');

const otherDniButton =
    document.getElementById('other-dni-button');

const edicionId =
    <?= (int) $edicionId ?>;


let currentDni = '';


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
                        edicion_id: edicionId
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


            /* =============================================
               PERSONA NUEVA
               ============================================= */

            if (data.estado === 'nueva') {

                window.location.href =
                    '?edicion=' +
                    encodeURIComponent(edicionId) +
                    '&dni=' +
                    encodeURIComponent(dni);

                return;
            }


            /* =============================================
               YA INSCRIPTO
               ============================================= */

            if (data.estado === 'ya_inscripto') {

                dniStartForm.hidden = true;

                alreadyRegistered.hidden = false;

                return;
            }


            /* =============================================
               PERSONA EXISTENTE
               ============================================= */

            if (
                data.estado ===
                'requiere_verificacion'
            ) {

                currentDni = dni;

                maskedEmail.textContent =
                    data.email_enmascarado;

                dniStartForm.hidden = true;

                existingPerson.hidden = false;

                return;
            }


            throw new Error(
                'No pudimos continuar con la inscripción.'
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


/* =========================================================
   OTRO DNI
   ========================================================= */

otherDniButton.addEventListener(
    'click',
    () => {

        currentDni = '';

        existingPerson.hidden = true;

        dniStartForm.hidden = false;

        dniStart.value = '';

        dniStart.focus();

    }
);


/* =========================================================
   ENVIAR CÓDIGO
   ========================================================= */

sendCodeButton.addEventListener(
    'click',
    async () => {

        if (!currentDni) {
            return;
        }

        sendCodeButton.disabled = true;
        sendCodeButton.textContent = 'Enviando...';

        try {

            const response = await fetch(
                '../api/enviar-codigo.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json'
                    },

                    body: JSON.stringify({
                        dni: currentDni,
                        edicion_id: edicionId
                    })
                }
            );

            const data = await response.json();


            if (!response.ok || !data.ok) {
                throw new Error(
                    data.message ||
                    'No pudimos enviar el código.'
                );
            }


            currentVerificationId =
                data.verificacion_id;

            currentMaskedEmail =
                maskedEmail.textContent;

            existingPerson.hidden = true;

            verificationEmail.textContent =
                currentMaskedEmail;

            verificationStep.hidden = false;

            verificationCode.value = '';
            verificationError.textContent = '';

            verificationCode.focus();


            /*
            * TEMPORAL · SOLO DESARROLLO
            */

            console.log(
                'CÓDIGO DEV:',
                data.codigo_dev
            );


        } catch (error) {

            alert(error.message);

        } finally {

            sendCodeButton.disabled = false;
            sendCodeButton.textContent = 'Enviar código';

        }

    }
);

verificationForm.addEventListener(
    'submit',
    async (event) => {

        event.preventDefault();

        const codigo =
            verificationCode.value.trim();

        if (codigo.length !== 6) {

            verificationError.textContent =
                'Ingresá los 6 dígitos.';

            verificationCode.focus();

            return;
        }

        verificationSubmit.disabled = true;
        verificationSubmit.textContent =
            'Verificando...';

        try {

            const response = await fetch(
                '../api/verificar-codigo.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json'
                    },

                    body: JSON.stringify({
                        verificacion_id: currentVerificationId,
                        codigo: codigo
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {

                throw new Error(
                    data.message ||
                    'No pudimos verificar el código.'
                );
            }

            window.location.href =
                'exito.php?edicion=' +
                encodeURIComponent(data.edicion_id);

        } catch (error) {

            verificationError.textContent =
                error.message;

        } finally {

            verificationSubmit.disabled = false;
            verificationSubmit.textContent =
                'Verificar y continuar';

        }

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

const universityOther =
    document.getElementById('universidad_otra');

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
        universityOther.value = query;

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

                                    universityOther.value = '';

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