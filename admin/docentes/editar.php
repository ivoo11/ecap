<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../../app/services/AdminForm.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$docente = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT d.*,p.nombre,p.apellido,p.dni,p.email FROM docentes d JOIN personas p ON p.id=d.persona_id WHERE d.id=?');
    $stmt->execute([$id]); $docente = $stmt->fetch();
    if (!$docente) { http_response_code(404); exit('Docente no encontrado.'); }
}
$valores = array_fill_keys(['nombre','apellido','dni','email','titulo_profesional','bio'], '');
if ($docente) foreach ($valores as $key => $_) $valores[$key] = $docente[$key] ?? '';
$activo = $docente ? (bool)$docente['activo'] : true;
$errores = []; $personaExistente = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminForm::csrf();
    foreach ($valores as $key => $_) {
        if (!$docente || in_array($key, ['titulo_profesional','bio'], true)) $valores[$key] = AdminForm::text($key);
    }
    $activo = AdminForm::text('activo') === '1';
    if (!$docente) {
        if (!preg_match('/^[0-9]{7,9}$/D', $valores['dni'])) $errores[] = 'Ingresá un DNI de 7 a 9 dígitos, sin puntos.';
        $stmt = $pdo->prepare('SELECT id,nombre,apellido,email,estado FROM personas WHERE dni=?');
        $stmt->execute([$valores['dni']]); $personaExistente = $stmt->fetch() ?: null;
        if ($personaExistente) {
            if ($personaExistente['estado'] !== 'activo') $errores[] = 'La persona está inactiva. Revisá su situación antes de vincularla.';
            if (AdminForm::text('vincular') !== (string)$personaExistente['id']) $errores[] = 'Ese DNI ya está registrado. Confirmá la vinculación para reutilizar la persona existente.';
        } else {
            foreach (['nombre','apellido'] as $key) if ($valores[$key] === '' || mb_strlen($valores[$key]) > 100) $errores[] = 'Completá nombre y apellido (hasta 100 caracteres cada uno).';
            if (!filter_var($valores['email'], FILTER_VALIDATE_EMAIL) || strlen($valores['email']) > 190) $errores[] = 'Ingresá un correo válido de hasta 190 caracteres.';
        }
    }
    if (mb_strlen($valores['titulo_profesional']) > 100 || strlen($valores['bio']) > 65535) $errores[] = 'El título profesional o la biografía exceden el largo permitido.';
    $fotoNueva = null;
    if (!$errores) try {
        $pdo->beginTransaction();
        if ($docente) {
            $stmt = $pdo->prepare('SELECT id,foto FROM docentes WHERE id=? FOR UPDATE'); $stmt->execute([$id]);
            $actual = $stmt->fetch();
            if (!$actual) throw new DomainException('El perfil ya no está disponible.');
            $personaId = $docente['persona_id'];
        } elseif ($personaExistente) {
            $stmt = $pdo->prepare("SELECT id FROM personas WHERE id=? AND dni=? AND estado='activo' FOR UPDATE");
            $stmt->execute([$personaExistente['id'],$valores['dni']]);
            $personaId = $stmt->fetchColumn();
            if (!$personaId) throw new DomainException('La persona cambió. Volvé a revisar los datos.');
        } else {
            $pdo->prepare('INSERT INTO personas(nombre,apellido,dni,email) VALUES(?,?,?,?)')->execute([$valores['nombre'],$valores['apellido'],$valores['dni'],$valores['email']]);
            $personaId = (int)$pdo->lastInsertId();
        }
        if (!$docente) {
            $stmt = $pdo->prepare('SELECT id FROM docentes WHERE persona_id=?'); $stmt->execute([$personaId]);
            if ($stmt->fetchColumn()) throw new DomainException('Esta persona ya tiene un perfil docente. Buscalo en el listado.');
        }
        $fotoNueva = AdminForm::image('foto', 'docentes');
        $foto = $fotoNueva ?? (AdminForm::text('quitar_foto') === '1' ? null : ($actual['foto'] ?? null));
        $datos = [$valores['titulo_profesional'] ?: null,$valores['bio'] ?: null,$foto,(int)$activo];
        if ($docente) $pdo->prepare('UPDATE docentes SET titulo_profesional=?,bio=?,foto=?,activo=? WHERE id=?')->execute([...$datos,$id]);
        else {
            $pdo->prepare('INSERT INTO docentes(titulo_profesional,bio,foto,activo,persona_id) VALUES(?,?,?,?,?)')->execute([...$datos,$personaId]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit(); Csrf::regenerate(); header('Location: /admin/docentes/editar.php?id='.$id.'&guardado=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        AdminForm::discardImage($fotoNueva, 'docentes');
        $errores[] = $e instanceof DomainException ? $e->getMessage() : 'No se pudo guardar. Revisá si ese DNI ya fue cargado y volvé a intentarlo.';
        if (!$e instanceof DomainException) error_log('Error guardando docente ECAP: '.$e->getMessage());
    }
}
$pageTitle = $docente ? 'Editar docente' : 'Crear docente'; $pageSection = 'docentes';
require __DIR__ . '/../includes/header.php';
?>
<section class="admin-page admin-page--editor">
<div class="activity-create-nav"><a class="activity-create-back" href="/admin/docentes/">← Docentes</a></div>
<header class="admin-page__header"><div><h1><?= $pageTitle ?></h1><p>Este perfil se puede asignar a distintas ediciones. No crea una cuenta de acceso ni envía correos.</p></div></header>
<?php if (isset($_GET['guardado'])): ?><div class="edition-message edition-message--success" role="status">Perfil guardado. Ya podés seleccionarlo en la configuración de una edición.</div><?php endif; ?>
<?php if ($errores): ?><div class="admin-alert admin-alert--error" role="alert"><ul><?php foreach ($errores as $error): ?><li><?= AdminForm::escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="activity-create-form"><section class="activity-editor-surface"><div class="activity-editor-body">
<?= Csrf::field() ?>
<p>Nombre, apellido, título profesional, biografía y foto se muestran en la web. DNI y correo son datos internos.</p>
<?php foreach (['dni'=>'DNI','nombre'=>'Nombre','apellido'=>'Apellido','email'=>'Correo electrónico','titulo_profesional'=>'Título profesional (ej. Dra.)'] as $key=>$label): ?>
<div class="activity-editor-field"><label for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" type="<?= $key==='email'?'email':'text' ?>" maxlength="<?= $key==='email'?190:($key==='dni'?9:100) ?>" value="<?= AdminForm::escape($valores[$key]) ?>" <?= $docente && $key!=='titulo_profesional' ? 'readonly' : '' ?> <?= $key==='dni'?'required':'' ?>></div>
<?php endforeach; ?>
<?php if ($docente): ?><p>Nombre, apellido, DNI y correo se editan en la ficha compartida de la persona.</p><p><a class="activity-action-secondary" href="/admin/alumnos/editar.php?id=<?= (int)$docente['persona_id'] ?>&amp;docente=<?= (int)$id ?>">Editar datos personales</a></p><?php endif; ?>
<?php if ($personaExistente): ?><p><?= AdminForm::escape($personaExistente['nombre'].' '.$personaExistente['apellido']) ?> ya está registrado con este DNI.</p><p><label><input type="checkbox" name="vincular" value="<?= (int)$personaExistente['id'] ?>" required> Vincular esa persona conservando sus datos existentes.</label></p><?php endif; ?>
<div class="activity-editor-field"><label for="bio">Biografía</label><textarea id="bio" name="bio" rows="7"><?= AdminForm::escape($valores['bio']) ?></textarea></div>
<?php if (!empty($docente['foto'])): ?><p><a href="<?= AdminForm::escape(PublicImage::url('docentes', $docente['foto'])) ?>" target="_blank" rel="noopener">Ver foto actual</a></p><p><label><input type="checkbox" name="quitar_foto" value="1"> Quitar foto del perfil</label></p><?php endif; ?>
<div class="activity-editor-field"><label for="foto">Foto (JPG, PNG o WebP, hasta 2 MB)</label><input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><p>Si hay un error al guardar, seleccioná el archivo nuevamente.</p></div>
<p><label><input type="checkbox" name="activo" value="1" <?= $activo?'checked':'' ?>> Perfil activo</label></p><p>Un perfil inactivo deja de mostrarse en las actividades públicas.</p>
</div><footer class="activity-editor-actions"><a class="activity-action-secondary" href="/admin/docentes/">Volver</a><button class="activity-action-primary" type="submit">Guardar docente</button></footer></section></form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
