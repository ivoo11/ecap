<?php // Vista del listado; los cambios de estado conservan el controlador existente.
if (!isset($actividades,$estadosInscripcion)) { http_response_code(404); exit; }
?>
<section class="admin-page actividades-page">
<header class="actividades-heading"><h1>Actividades</h1><a class="admin-button" href="/admin/actividades/nueva.php">Nueva actividad</a></header>
<?php if($errorEstado): ?><div class="admin-alert admin-alert--error" role="alert"><?= AdminForm::escape($errorEstado) ?></div><?php endif; ?>
<?php if(isset($_GET['estado_guardado']) && !$errorEstado): ?><p class="actividades-feedback" role="status">Inscripciones guardadas.</p><?php endif; ?>
<div class="actividades-tools" hidden>
<label class="actividades-search"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><input id="actividad-buscar" type="search" aria-label="Buscar actividad" placeholder="Buscar actividad" autocorrect="off" autocapitalize="none" autocomplete="off" spellcheck="false"></label>
<div class="actividades-filtros" role="group" aria-label="Filtrar actividades"><?php foreach(['todas'=>'Todas','proxima'=>'Próximas','en_curso'=>'En curso','finalizada'=>'Finalizadas'] as $key=>$label): ?><button type="button" data-filter="<?= $key ?>" aria-pressed="<?= $key==='todas'?'true':'false' ?>"><?= $label ?></button><?php endforeach; ?></div>
</div>
<?php if(!$actividades): ?><div class="admin-card"><h2>Todavía no hay actividades</h2></div><?php else: ?>
<div class="actividades-lista"><table><thead><tr><th>Actividad</th><th>Fecha</th><th>Estado</th><th>Inscripciones</th><th><span class="actividades-sr">Acciones</span></th></tr></thead><tbody>
<?php foreach($actividades as $a):
$estado=$a['edicion_estado']??'';
$proxima=in_array($estado,['proximamente','inscripcion_abierta','inscripcion_cerrada','cupo_completo'],true);
$visual=$proxima?'proxima':($estado?:'sin_edicion');
$etiqueta=$proxima?'Próxima':($estado?adminEstadoEdicion($estado):'Sin edición');
$rapido=$proxima && strtotime($a['fecha_inicio']??'')>time();
$seleccion=$estado==='cupo_completo'?'inscripcion_abierta':$estado;
?>
<tr data-actividad data-status="<?= AdminForm::escape($visual) ?>" data-search="<?= AdminForm::escape($a['titulo']) ?>">
<td class="actividad-name"><strong><?= AdminForm::escape($a['titulo']) ?></strong><p><?= AdminForm::escape($a['tipo_actividad']) ?><?= $a['modalidad']?' · '.AdminForm::escape(adminModalidad($a['modalidad'])):'' ?></p><?php if($a['estado']!=='publicada'): ?><p><?= $a['estado']==='archivada'?'Archivada':'No publicada' ?></p><?php endif; ?></td>
<td class="actividad-date"><?= $a['fecha_inicio']?date('d/m/Y',strtotime($a['fecha_inicio'])):'Sin fecha' ?></td>
<td class="actividad-status"><span class="actividad-pill actividad-pill--<?= AdminForm::escape($visual) ?>"><?= AdminForm::escape($etiqueta) ?></span></td>
<td class="actividad-enrollment">
<?php if($rapido): ?><form method="post" class="actividad-state-form">
<?= Csrf::field() ?><input type="hidden" name="edicion_id" value="<?= (int)$a['edicion_id'] ?>"><input type="hidden" name="estado_anterior" value="<?= AdminForm::escape($estado) ?>">
<label class="actividad-mobile-label" for="estado-<?= (int)$a['edicion_id'] ?>">Inscripciones</label>
<select name="estado" id="estado-<?= (int)$a['edicion_id'] ?>" aria-label="<?= AdminForm::escape('Inscripciones: '.$a['titulo']) ?>"><?php foreach($estadosInscripcion as $value=>$label): ?><option value="<?= $value ?>" <?= $seleccion===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select>
<?php if($estado==='cupo_completo'): ?><span class="actividad-capacidad">Cupos agotados</span><?php endif; ?>
<div class="actividad-save"><button type="submit">Guardar</button><button type="reset">Cancelar</button></div></form>
<?php else: ?><span class="actividad-mobile-label">Inscripciones</span><span class="actividad-enrollment-label"><?= in_array($estado,['en_curso','finalizada','cancelada'],true)?'Cerradas':'No habilitadas' ?></span><?php endif; ?>
</td>
<td class="actividad-action"><a href="/admin/actividades/editar.php?id=<?= (int)$a['id'] ?>" aria-label="<?= AdminForm::escape('Gestionar '.$a['titulo']) ?>">Gestionar</a></td>
</tr>
<?php endforeach; ?>
</tbody></table><p class="actividades-empty" hidden>No hay actividades para esta búsqueda.</p></div>
<p class="actividades-sr" id="actividades-resultados" role="status" aria-live="polite"></p>
<?php endif; ?>
</section>
<script src="/assets/admin/js/actividades-listado.js?v=<?= filemtime(__DIR__.'/../../assets/admin/js/actividades-listado.js') ?>" defer></script>
