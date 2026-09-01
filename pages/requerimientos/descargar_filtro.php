<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Obtener filtros
$filtro = $_GET['filtro'] ?? '';
$criterio = $_GET['criterio'] ?? '';
$tecnico = Session::get('user_name') ?? 'TECNICO';

// Construir consulta
$sql = "SELECT r.*, 
               COALESCE(pc.activo, i.activo, u.activo, o.activo) as activo,
               COALESCE(pc.tipo, i.tipo, u.tipo, o.tipo) as tipo_equipo
        FROM t_requerimiento r
        LEFT JOIN t_inventpc pc ON r.inventario = pc.inventario
        LEFT JOIN t_impresores i ON r.inventario = i.inventario
        LEFT JOIN t_ups u ON r.inventario = u.inventario
        LEFT JOIN t_otros o ON r.inventario = o.inventario";

if (!empty($filtro) && !empty($criterio)) {
    $sql .= " WHERE r.$filtro LIKE '%$criterio%'";
}

$sql .= " ORDER BY r.requerimiento DESC";
$requerimientos = $db->fetchAll($sql);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Req_Filtrados_' . date('d-m-Y') . '.xls"');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
            xmlns:x="urn:schemas-microsoft-com:office:excel" 
            xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';

echo '<table border="1" cellpadding="5" cellspacing="0">';

// Encabezado institucional
$columnas = ['CENTRO DE COSTO', 'RESPONSABLE', 'UBICACION DEL EQUIPO', 'TELEFONO', 'EQUIPO', 
             'NUMERO DE INVENTARIO', 'DESCRIPCION', 'TIPO DE SERVICIO', 'TIPO DE ATENCION', 
             'ESTATUS', 'NUMERO DE REQUERIMIENTO'];

echo '<tr>';
echo '<td colspan="' . count($columnas) . '" style="text-align:center; font-size:14px; font-weight:bold; font-family:Arial;">';
echo 'INSTITUTO SALVADOREÑO DEL SEGURO SOCIAL<br>';
echo 'HOSPITAL ZACAMIL<br>';
echo 'REPORTE DE REQUERIMIENTOS<br>';
echo 'FECHA: ' . date('d/m/Y');
echo '</td>';
echo '</tr>';

// Encabezados de columnas
echo '<tr style="background:#006699;">';
foreach ($columnas as $col) {
    echo '<th style="color:white; font-weight:bold; text-align:center; font-size:10px; font-family:Arial;">' . $col . '</th>';
}
echo '</tr>';

// Datos
if (!empty($requerimientos)) {
    foreach ($requerimientos as $row) {
        $estadoColor = ($row['estatus'] == 'FINALIZADO') ? '#006600' : '#CC6600';
        echo '<tr>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['cc'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['responsable'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['ubicacion'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['telefono'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['tipo_equipo'] ?? $row['tipo'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['inventario'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['falla'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['servicio'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['atencion'] ?? '') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial; font-weight:bold; color:' . $estadoColor . ';">' . htmlspecialchars($row['estatus'] ?? 'PENDIENTE') . '</td>';
        echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['requerimiento'] ?? '') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr>';
    echo '<td colspan="' . count($columnas) . '" style="font-size:10px; font-family:Arial; text-align:center;">No hay requerimientos que coincidan con el filtro</td>';
    echo '</tr>';
}

// Pie
echo '<tr>';
echo '<td colspan="' . count($columnas) . '" style="font-size:10px; font-weight:bold; font-family:Arial; padding:8px;">';
echo 'TECNICO RESPONSABLE: ' . htmlspecialchars($tecnico) . ' TELEFONO: _________________';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</body></html>';
exit;
?>