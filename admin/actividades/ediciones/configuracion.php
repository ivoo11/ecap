<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../../app/services/EstadoEdiciones.php';

$edicionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$actividadId = filter_input(INPUT_GET, 'actividad', FILTER_VALIDATE_INT) ?: null;
$edicion = null;
if ($edicionId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM ediciones WHERE id = ?');
    $stmt->execute([$edicionId]);
    $edicion = $stmt->fetch();
    if (!$edicion) { http_response_code(404); exit('Edición no encontrada.'); }
    $actividadId = (int) $edicion['actividad_id'];
}
$stmt = $pdo->prepare('SELECT a.*, t.nombre AS tipo FROM actividades a JOIN tipos_actividad t ON t.id = a.tipo_actividad_id WHERE a.id = ?');
$stmt->execute([$actividadId]);
$actividad = $stmt->fetch();
if (!$actividad) { http_response_code(404); exit('Actividad no encontrada.'); }

$asignados = [];
if ($edicionId) {
    $stmt = $pdo->prepare('SELECT docente_id FROM edicion_docentes WHERE edicion_id = ? ORDER BY orden, docente_id');
    $stmt->execute([$edicionId]);
    $asignados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
$docentes = $pdo->query('SELECT d.id, d.activo, d.titulo_profesional, p.nombre, p.apellido FROM docentes d JOIN personas p ON p.id=d.persona_id ORDER BY p.apellido,p.nombre')->fetchAll();
$estados = ['borrador'=>'Borrador', 'proximamente'=>'Inscripciones próximamente', 'inscripcion_abierta'=>'Inscripciones abiertas', 'inscripcion_cerrada'=>'Inscripciones cerradas', 'cancelada'=>'Cancelada'];
$valores = [
    'fecha_inicio' => $edicion ? substr(str_replace(' ', 'T', $edicion['fecha_inicio']), 0, 16) : '',
    'fecha_fin' => !empty($edicion['fecha_fin']) ? substr(str_replace(' ', 'T', $edicion['fecha_fin']), 0, 16) : '',
    'modalidad' => $edicion['modalidad'] ?? 'online',
    'cupo_maximo' => $edicion ? (string) ($edicion['cupo_maximo'] ?? '') : '',
    'estado' => ($edicion['estado'] ?? '') === 'cupo_completo' ? 'inscripcion_abierta' : ($edicion['estado'] ?? 'borrador'),
    'zoom_url' => $edicion['zoom_url'] ?? '',
    'zoom_meeting_id' => $edicion['zoom_meeting_id'] ?? '',
    'zoom_passcode' => $edicion['zoom_passcode'] ?? '',
];
$estadoAutomatico = in_array($edicion['estado'] ?? '', ['en_curso','finalizada'], true);
$seleccionados = $asignados;
$publicar = false;
$errores = [];
function escaparEdicion(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function fechaFormularioEdicion(string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    return $date && $date->format('Y-m-d\TH:i') === $value ? $date : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Recargá la página.');
    }
    foreach ($valores as $key => $_) $valores[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    if ($estadoAutomatico) $valores['estado'] = $edicion['estado'];
    $publicar = ($_POST['publicar'] ?? '') === '1';
    $seleccionados = [];
    if (!is_array($_POST['docentes'] ?? [])) $errores[] = 'La selección de docentes no es válida.';
    else foreach ($_POST['docentes'] ?? [] as $id) {
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) $errores[] = 'La selección de docentes no es válida.';
        else $seleccionados[] = (int) $id;
    }
    $seleccionados = array_values(array_unique($seleccionados));
    $permitidos = array_map('intval', array_column(array_filter($docentes, static fn(array $d): bool => (bool) $d['activo'] || in_array((int) $d['id'], $asignados, true)), 'id'));
    if (array_diff($seleccionados, $permitidos)) $errores[] = 'Seleccioná docentes disponibles en el listado.';
    $inicio = fechaFormularioEdicion($valores['fecha_inicio']);
    $fin = fechaFormularioEdicion($valores['fecha_fin']);
    if (!$inicio || !$fin || $fin <= $inicio) $errores[] = 'Indicá un inicio válido y una finalización posterior.';
    if (!in_array($valores['modalidad'], ['online','presencial','hibrida'], true)) $errores[] = 'Seleccioná una modalidad válida.';
    if (!$estadoAutomatico && !isset($estados[$valores['estado']])) $errores[] = 'Seleccioná un estado válido.';
    if ($inicio && $valores['estado'] === 'inscripcion_abierta' && $inicio <= new DateTimeImmutable()) $errores[] = 'No se puede abrir la inscripción de una edición que ya comenzó.';
    $cupo = null;
    if ($valores['cupo_maximo'] !== '') {
        $cupo = filter_var($valores['cupo_maximo'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>4294967295]]);
        if ($cupo === false) $errores[] = 'El cupo debe ser un número entero positivo, o quedar vacío para no limitarlo.';
    }
    if ($valores['zoom_url'] !== '' && (!filter_var($valores['zoom_url'], FILTER_VALIDATE_URL) || strtolower((string) parse_url($valores['zoom_url'], PHP_URL_SCHEME)) !== 'https' || strlen($valores['zoom_url']) > 500)) $errores[] = 'Ingresá un enlace de acceso HTTPS válido (hasta 500 caracteres).';
    if (mb_strlen($valores['zoom_meeting_id']) > 50 || mb_strlen($valores['zoom_passcode']) > 100) $errores[] = 'El ID o la clave de acceso exceden el largo permitido.';

    if (!$errores) {
        try {
            $pdo->beginTransaction();
            // La actividad serializa altas simultáneas de ediciones.
            $stmt = $pdo->prepare('SELECT id FROM actividades WHERE id = ? FOR UPDATE');
            $stmt->execute([$actividadId]);
            if (!$stmt->fetchColumn()) throw new DomainException('La actividad ya no está disponible.');
            $clases = [];
            if ($edicionId) {
                $stmt = $pdo->prepare('SELECT id,fecha_inicio,fecha_fin,estado FROM ediciones WHERE id = ? AND actividad_id = ? FOR UPDATE');
                $stmt->execute([$edicionId, $actividadId]);
                $edicionActual = $stmt->fetch();
                if (!$edicionActual) throw new DomainException('La edición ya no está disponible.');
                if (in_array($edicionActual['estado'], ['en_curso','finalizada'], true)) $valores['estado'] = $edicionActual['estado'];
                elseif ($estadoAutomatico) throw new DomainException('El estado cambió. Recargá la configuración.');
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE edicion_id = ? AND estado = 'confirmada'");
                $stmt->execute([$edicionId]);
                if ($cupo !== null && $cupo < (int) $stmt->fetchColumn()) throw new DomainException('El cupo no puede ser menor que la cantidad de inscripciones confirmadas.');
                $stmt = $pdo->prepare('SELECT id,fecha_inicio,fecha_fin FROM clases WHERE edicion_id = ? ORDER BY fecha_inicio FOR UPDATE');
                $stmt->execute([$edicionId]);
                $clases = $stmt->fetchAll();
                if (count($clases) > 1) foreach ($clases as $clase) {
                    if (new DateTimeImmutable($clase['fecha_inicio']) < $inicio || new DateTimeImmutable($clase['fecha_fin'] ?? $clase['fecha_inicio']) > $fin) throw new DomainException('El período de la edición debe incluir todas sus clases existentes.');
                }
            } else {
                $stmt = $pdo->prepare('SELECT id FROM ediciones WHERE actividad_id = ? AND fecha_inicio = ? LIMIT 1');
                $stmt->execute([$actividadId, $inicio->format('Y-m-d H:i:s')]);
                if ($stmt->fetchColumn()) throw new DomainException('Ya existe una edición de esta actividad con ese inicio.');
            }
            $datos = [$inicio->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), $valores['modalidad'], $cupo, $valores['estado'], $valores['zoom_url'] ?: null, $valores['zoom_meeting_id'] ?: null, $valores['zoom_passcode'] ?: null];
            if ($edicionId) {
                $pdo->prepare('UPDATE ediciones SET fecha_inicio=?,fecha_fin=?,modalidad=?,cupo_maximo=?,estado=?,zoom_url=?,zoom_meeting_id=?,zoom_passcode=? WHERE id=?')->execute([...$datos,$edicionId]);
            } else {
                $pdo->prepare('INSERT INTO ediciones(fecha_inicio,fecha_fin,modalidad,cupo_maximo,estado,zoom_url,zoom_meeting_id,zoom_passcode,actividad_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([...$datos,$actividadId]);
                $edicionId = (int) $pdo->lastInsertId();
            }
            // La primera clase alimenta el cron existente; no se crea otro circuito.
            if (!$clases) {
                $pdo->prepare('INSERT INTO clases(edicion_id,titulo,numero,fecha_inicio,fecha_fin) VALUES(?,?,1,?,?)')->execute([$edicionId,$actividad['titulo'],$datos[0],$datos[1]]);
            } elseif (count($clases) === 1 && ($edicionActual['fecha_inicio'] !== $datos[0] || $edicionActual['fecha_fin'] !== $datos[1])) {
                $pdo->prepare('UPDATE clases SET fecha_inicio=?,fecha_fin=? WHERE id=?')->execute([$datos[0],$datos[1],$clases[0]['id']]);
            }
            foreach (array_diff($asignados, $seleccionados) as $id) $pdo->prepare('DELETE FROM edicion_docentes WHERE edicion_id=? AND docente_id=?')->execute([$edicionId,$id]);
            foreach (array_diff($seleccionados, $asignados) as $id) {
                $stmt = $pdo->prepare('SELECT id FROM docentes WHERE id=? AND activo=1 FOR UPDATE');$stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new DomainException('Un docente seleccionado ya no está disponible.');
                $pdo->prepare('INSERT INTO edicion_docentes(edicion_id,docente_id,orden) VALUES(?,?,?)')->execute([$edicionId,$id,count($asignados)+1]);
            }
            if ($publicar) $pdo->prepare("UPDATE actividades SET estado='publicada' WHERE id=?")->execute([$actividadId]);
            $pdo->commit();
            Csrf::regenerate();
            header('Location: /admin/actividades/ediciones/configuracion.php?id=' . $edicionId . '&guardada=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = $e instanceof DomainException ? $e->getMessage() : 'No se pudo guardar la edición. Volvé a intentarlo.';
            if (!$e instanceof DomainException) error_log('Error guardando edición ECAP: ' . $e->getMessage());
            $edicionId = $edicion ? (int) $edicion['id'] : null;
        }
    }
}
$pageTitle = $edicion ? 'Configuración de edición' : 'Nueva edición';
$pageSection = 'actividades';
require __DIR__ . '/../../includes/header.php';
?>
<section class="admin-page admin-page--workspace">
    <div class="activity-workspace-back"><a href="/admin/actividades/editar.php?id=<?= (int) $actividadId ?>">← Volver a la actividad</a></div>
    <header class="activity-workspace-hero">
        <span class="activity-workspace-type"><?= escaparEdicion($actividad['tipo']) ?></span>
        <h1><?= escaparEdicion($actividad['titulo']) ?></h1>
    </header>
    <?php if ($edicion): ?>
    <nav class="edition-admin-nav" aria-label="Gestión de edición">
        <span class="edition-admin-nav__item is-active">Configuración y docentes</span>
        <a class="edition-admin-nav__item" href="/admin/actividades/ediciones/clases.php?id=<?= (int)$edicionId ?>">Clases</a>
        <a class="edition-admin-nav__item" href="/admin/actividades/ediciones/editar.php?id=<?= (int) $edicionId ?>">Alumnos</a>
    </nav>
    <?php endif; ?>
    <?php if ($errores): ?><div class="admin-alert admin-alert--error" role="alert"><strong>Revisá estos datos</strong><ul><?php foreach ($errores as $error): ?><li><?= escaparEdicion($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (isset($_GET['guardada']) && !$errores): ?><div class="edition-message edition-message--success" role="status">Edición guardada. No se enviaron correos al guardar esta configuración.</div><?php endif; ?>
    <div class="activity-workspace-content">
    <header class="activity-workspace-content__header"><h2><?= escaparEdicion($pageTitle) ?></h2><p>Definí cuándo se dicta y cómo se participa. Horarios de Argentina.</p></header>
    <form method="post" class="activity-edition-form">
        <?= Csrf::field() ?>
        <section class="activity-edition-section">
            <div class="activity-edition-section__intro"><h3>Fecha y horario</h3><p>Al crear la edición se agrega su primera clase. Si tiene una sola clase y cambiás las fechas de la edición, también se actualizan sus horarios. Si tiene varias, se conservan.</p></div>
            <div class="activity-edition-fields activity-edition-fields--two">
                <?php foreach (['fecha_inicio'=>'Inicio','fecha_fin'=>'Finalización'] as $key=>$label): ?>
                <div class="activity-editor-field"><label for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" type="datetime-local" value="<?= escaparEdicion($valores[$key]) ?>" required></div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="activity-edition-section">
            <div class="activity-edition-section__intro"><h3>Modalidad y cupo</h3><p>Dejá el cupo vacío si no tiene límite.</p></div>
            <div class="activity-edition-fields">
                <fieldset class="activity-type-field"><legend>Modalidad</legend><div class="activity-mode-options">
                    <?php foreach (['online'=>'Virtual','presencial'=>'Presencial','hibrida'=>'Híbrida'] as $value=>$label): ?><label class="activity-mode-option"><input type="radio" name="modalidad" value="<?= $value ?>" <?= $valores['modalidad']===$value?'checked':'' ?> required><span><?= $label ?></span></label><?php endforeach; ?>
                </div></fieldset>
                <div class="activity-editor-field"><label for="cupo_maximo">Cupo máximo</label><input id="cupo_maximo" name="cupo_maximo" type="number" min="1" value="<?= escaparEdicion($valores['cupo_maximo']) ?>" placeholder="Sin límite"></div>
            </div>
        </section>
        <section class="activity-edition-section">
            <div class="activity-edition-section__intro"><h3>Acceso al encuentro</h3><p>Estos datos se usan en los correos de confirmación y recordatorio. Podés completarlos después.</p></div>
            <div class="activity-edition-fields">
                <?php foreach (['zoom_url'=>'Enlace de acceso','zoom_meeting_id'=>'ID de reunión','zoom_passcode'=>'Clave de acceso'] as $key=>$label): ?><div class="activity-editor-field"><label for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" type="<?= $key==='zoom_url'?'url':'text' ?>" maxlength="<?= $key==='zoom_url'?500:($key==='zoom_meeting_id'?50:100) ?>" value="<?= escaparEdicion($valores[$key]) ?>"></div><?php endforeach; ?>
            </div>
        </section>
        <section class="activity-edition-section">
            <div class="activity-edition-section__intro"><h3>Docentes</h3><p>Seleccioná perfiles existentes para vincularlos a esta edición.</p><p><a href="/admin/docentes/editar.php" target="_blank" rel="noopener">Crear docente en otra pestaña</a>. Guardá esta edición y recargá para actualizar el listado.</p></div>
            <fieldset class="activity-type-field"><legend>Docentes de la edición</legend>
                <?php $visibles=0; foreach ($docentes as $docente): if (!$docente['activo'] && !in_array((int)$docente['id'],$asignados,true)) continue; $visibles++; ?>
                <p><label><input type="checkbox" name="docentes[]" value="<?= (int)$docente['id'] ?>" <?= in_array((int)$docente['id'],$seleccionados,true)?'checked':'' ?>> <?= escaparEdicion(trim($docente['titulo_profesional'].' '.$docente['nombre'].' '.$docente['apellido'])) ?><?= !$docente['activo']?' (inactivo)':'' ?></label></p>
                <?php endforeach; if (!$visibles): ?><p>No hay perfiles docentes disponibles todavía.</p><?php endif; ?>
            </fieldset>
        </section>
        <section class="activity-edition-section">
            <div class="activity-edition-section__intro"><h3>Inscripción y publicación</h3><p>Elegí si las inscripciones están próximas, abiertas o cerradas. Con inscripciones abiertas, «Cupos agotados» se muestra automáticamente al alcanzar el cupo; si vuelve a haber lugar, se muestran abiertas otra vez. Un borrador no se muestra en la web y la actividad también debe estar publicada.</p></div>
            <div class="activity-edition-fields">
                <?php if ($estadoAutomatico): ?>
                <p class="admin-status"><?= $edicion['estado']==='en_curso'?'En curso':'Finalizada' ?> · Automático</p>
                <?php else: ?>
                <div class="admin-field"><label for="estado">Estado de la edición</label><select id="estado" name="estado" class="admin-select"><?php foreach ($estados as $value=>$label): ?><option value="<?= $value ?>" <?= $valores['estado']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
                <p class="admin-text-muted">En curso se asigna al llegar el inicio; Finalizada, 30 minutos después del final. Estos estados no se seleccionan manualmente.</p>
                <?php if ($actividad['estado'] !== 'publicada'): ?><p><label><input type="checkbox" name="publicar" value="1" <?= $publicar?'checked':'' ?>> Publicar también la actividad</label></p><p class="admin-text-muted">La publicación de la actividad se aplica a todas sus ediciones visibles.</p><?php else: ?><p>La actividad ya está publicada.</p><?php endif; ?>
            </div>
        </section>
        <footer class="activity-edition-actions"><a class="activity-action-secondary" href="/admin/actividades/editar.php?id=<?= (int)$actividadId ?>">Volver</a><button type="submit" class="activity-action-primary"><?= $edicion?'Guardar cambios':'Crear edición' ?></button></footer>
    </form>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
