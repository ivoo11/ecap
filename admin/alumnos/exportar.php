<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/AlumnoExportacion.php';
header('Cache-Control: no-store, private');
$error='';$cols=['apellido','nombre','dni','email'];$f=AlumnoEstadisticas::filtros([]);$total=0;$valid=false;
try{
    $f=AlumnoEstadisticas::filtros($_GET);
    [$where,$params]=AlumnoEstadisticas::consulta($f);
    $q=$pdo->prepare('SELECT COUNT(*) FROM personas p '.$where);$q->execute($params);$total=(int)$q->fetchColumn();$valid=true;
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::validate(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null))throw new DomainException('La sesión del formulario venció. Volvé a intentar.');
        $cols=AlumnoExportacion::columnas($_POST['campos']??null);
        $format=$_POST['formato']??'';
        if(!in_array($format,['xlsx','pdf'],true))throw new DomainException('Elegí Excel o PDF.');
        if(!$total)throw new DomainException('No hay personas para descargar con estos filtros.');
        $rows=AlumnoExportacion::datos($pdo,$f,$cols);
        $content=$format==='xlsx'?AlumnoExportacion::xlsx($cols,$rows):AlumnoExportacion::pdf($cols,$rows);
        header('Content-Type: '.($format==='xlsx'?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'application/pdf'));
        header('Content-Disposition: attachment; filename="ecap-personas-'.date('Y-m-d').'.'.$format.'"');
        header('X-Content-Type-Options: nosniff');echo $content;exit;
    }
}catch(DomainException $e){$error=$e->getMessage();}catch(Throwable $e){error_log('ECAP exportación de alumnos: '.get_class($e));$error='No pudimos generar la descarga. Volvé a intentar.';}
$pageTitle='Descargar personas';$pageSection='alumnos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page alumno-page">
<header class="admin-page__header"><div><h1>Descargar personas</h1><p><?= $total ?> personas del listado filtrado, incluyendo todas sus páginas.</p></div><a class="activity-action-secondary" href="/admin/alumnos/?<?= AdminForm::escape(http_build_query($f)) ?>">Volver al listado</a></header>
<?php if($error!==''):?><p role="alert"><?= AdminForm::escape($error) ?></p><?php endif;?>
<form method="post" class="admin-card" action="?<?= AdminForm::escape(http_build_query($f)) ?>">
<?= Csrf::field() ?>
<fieldset class="alumno-export-fields"><legend>Elegí los datos que querés incluir</legend>
<?php foreach(AlumnoExportacion::CAMPOS as $key=>$label):?><label><input type="checkbox" name="campos[]" value="<?= $key ?>" <?= in_array($key,$cols,true)?'checked':'' ?>> <?= AdminForm::escape($label) ?></label><?php endforeach;?>
</fieldset>
<p class="admin-text-muted">Excel: una fila por persona, con encabezados y filtros. PDF: fichas legibles con los datos seleccionados. La cantidad de inscripciones incluye todos sus estados y actividades.</p>
<div class="alumno-actions"><button class="admin-button" type="submit" name="formato" value="xlsx" <?= !$valid||!$total?'disabled':'' ?>>Descargar Excel</button><button class="admin-button admin-button--secondary" type="submit" name="formato" value="pdf" <?= !$valid||!$total?'disabled':'' ?>>Descargar PDF</button></div>
</form></section>
<?php require __DIR__.'/../includes/footer.php';?>
