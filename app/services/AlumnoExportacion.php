<?php
declare(strict_types=1);
require_once __DIR__.'/AlumnoEstadisticas.php';

final class AlumnoExportacion
{
    public const CAMPOS=['id'=>'ID de persona','apellido'=>'Apellido','nombre'=>'Nombre','dni'=>'DNI','email'=>'Correo','telefono'=>'Teléfono','universidad'=>'Universidad','universidad_otra'=>'Universidad indicada (otra)','ambitos'=>'Ámbitos profesionales','estado'=>'Estado','creado_en'=>'Alta en ECAP','actualizado_en'=>'Última actualización','inscripciones'=>'Cantidad de inscripciones'];
    public static function columnas(mixed $input):array
    {
        if(!is_array($input) || !$input || count($input)>count(self::CAMPOS))throw new DomainException('Seleccioná al menos un dato para descargar.');
        foreach($input as $key)if(!is_string($key) || !isset(self::CAMPOS[$key]))throw new DomainException('La selección de datos no es válida.');
        return array_keys(array_intersect_key(self::CAMPOS,array_flip($input)));
    }
    public static function datos(PDO $pdo,array $f,array $cols):array
    {
        [$where,$params]=AlumnoEstadisticas::consulta($f);
        $q=$pdo->prepare('SELECT COUNT(*) FROM personas p '.$where);$q->execute($params);
        if((int)$q->fetchColumn()>5000)throw new DomainException('El resultado supera las 5.000 personas. Acotá los filtros para descargarlo.');
        $select=[];
        foreach($cols as $c)$select[]=match($c){
            'universidad'=>'u.nombre AS universidad',
            'ambitos'=>"(SELECT GROUP_CONCAT(ap.nombre ORDER BY ap.nombre SEPARATOR '; ') FROM persona_ambitos pa JOIN ambitos_profesionales ap ON ap.id=pa.ambito_id WHERE pa.persona_id=p.id) AS ambitos",
            'inscripciones'=>'(SELECT COUNT(*) FROM inscripciones i WHERE i.persona_id=p.id) AS inscripciones',
            default=>'p.'.$c,
        };
        $q=$pdo->prepare('SELECT '.implode(',',$select).' FROM personas p LEFT JOIN universidades u ON u.id=p.universidad_id '.$where.' ORDER BY p.apellido,p.nombre,p.id LIMIT 5001');$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)>5000)throw new DomainException('Acotá los filtros a un máximo de 5.000 personas.');
        foreach($rows as &$row)foreach($row as $key=>&$value){
            if(in_array($key,['creado_en','actualizado_en'],true) && $value)$value=(new DateTimeImmutable($value))->format('d/m/Y H:i');
            elseif($key==='estado')$value=$value==='activo'?'Activo':'Inactivo';
            $value=(string)($value??'');
        }
        return $rows;
    }
    private static function xml(string $value):string
    {
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$value),ENT_XML1|ENT_QUOTES,'UTF-8');
    }
    public static function xlsx(array $cols,array $rows):string
    {
        $file=tempnam(sys_get_temp_dir(),'ecap_export_');
        if($file===false)throw new RuntimeException('No se pudo preparar la descarga.');
        try{
            $zip=new ZipArchive();if($zip->open($file,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo preparar Excel.');
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Personas" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml','<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF101D30"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
            foreach($cols as $i=>$c){$n=$i+1;$width=in_array($c,['email','universidad','universidad_otra','ambitos'])?40:24;$sheet.='<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';}
            $sheet.='</cols><sheetData>';$all=[array_map(fn($c)=>self::CAMPOS[$c],$cols)];
            foreach($rows as $row)$all[]=array_map(fn($c)=>(string)$row[$c],$cols);
            foreach($all as $i=>$values){$r=$i+1;$height=32;foreach($values as $j=>$value){$chars=in_array($cols[$j],['email','universidad','universidad_otra','ambitos'])?36:20;$height=max($height,18*(ceil(mb_strlen($value)/$chars)+1));}$sheet.='<row r="'.$r.'" ht="'.$height.'" customHeight="1">';foreach($values as $j=>$v){$ref=chr(65+$j).$r;
                // Inline strings preserve leading zeros and never execute user data as formulas.
                $sheet.='<c r="'.$ref.'" t="inlineStr" s="'.($i===0?1:0).'"><is><t xml:space="preserve">'.self::xml($v).'</t></is></c>';}
                $sheet.='</row>';
            }
            $sheet.='</sheetData><autoFilter ref="A1:'.chr(64+count($cols)).count($all).'"/></worksheet>';
            $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);if(!$zip->close())throw new RuntimeException('No se pudo cerrar el archivo Excel.');
            $result=file_get_contents($file);if($result===false)throw new RuntimeException('No se pudo leer Excel.');return $result;
        }finally{unlink($file);}
    }
    public static function pdf(array $cols,array $rows):string
    {
        $cache=sys_get_temp_dir().'/ecap-export-cache';
        if(!is_dir($cache) && !mkdir($cache,0700,true) && !is_dir($cache))throw new RuntimeException('No se pudo preparar el PDF.');
        $pdf=new \Mpdf\Mpdf(['format'=>'A4','margin_left'=>16,'margin_right'=>16,'margin_top'=>18,'margin_bottom'=>18,'tempDir'=>$cache]);
        $pdf->SetTitle('ECAP - Listado de personas');$pdf->SetHTMLFooter('<div style="text-align:right;font-size:9px;color:#697586">ECAP · {PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML('<style>body{font-family:dejavusans;color:#101d30;font-size:10pt}h1{font-size:20pt}table{width:100%;border-collapse:collapse;margin-bottom:16px;page-break-inside:avoid}td{padding:6px;border-bottom:1px solid #e3e6eb}td.label{width:32%;color:#697586}th{text-align:left;background:#eef0f4;padding:8px}</style><h1>ECAP · Listado de personas</h1><p>'.count($rows).' personas · '.date('d/m/Y H:i').' (Argentina)</p>');
        foreach($rows as $i=>$row){$html='<table><tr><th colspan="2">Persona '.($i+1).'</th></tr>';foreach($cols as $c)$html.='<tr><td class="label">'.self::xml(self::CAMPOS[$c]).'</td><td>'.self::xml($row[$c]===''?'Sin dato':$row[$c]).'</td></tr>';$pdf->WriteHTML($html.'</table>');}
        return $pdf->Output('','S');
    }
}
