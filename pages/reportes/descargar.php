<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Obtener parámetros
$tipo = $_GET['tipo'] ?? '';
$filtro = $_GET['filtro'] ?? '';
$criterio = $_GET['criterio'] ?? '';
$fecha_ini = $_GET['fecha_ini'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$usuario = $_GET['usuario'] ?? '';
$tecnico = Session::get('user_name') ?? 'TECNICO';

// ============================================
// SI ES REPORTE DE REQUERIMIENTOS
// ============================================
if ($tipo == 'REQ' || isset($_GET['fecha_ini'])) {
    $sql = "SELECT r.*, 
                   COALESCE(pc.activo, i.activo, u.activo, o.activo) as activo,
                   COALESCE(pc.tipo, i.tipo, u.tipo, o.tipo) as tipo_equipo
            FROM t_requerimiento r
            LEFT JOIN t_inventpc pc ON r.inventario = pc.inventario
            LEFT JOIN t_impresores i ON r.inventario = i.inventario
            LEFT JOIN t_ups u ON r.inventario = u.inventario
            LEFT JOIN t_otros o ON r.inventario = o.inventario
            WHERE r.insertdate BETWEEN ? AND ?";
    
    $params = [$fecha_ini, $fecha_fin];
    
    if (!empty($usuario)) {
        $sql .= " AND r.Insertuser = ?";
        $params[] = $usuario;
    }
    
    $sql .= " ORDER BY r.requerimiento DESC";
    $requerimientos = $db->fetchAll($sql, $params);
    
    // ============================================
    // GENERAR EXCEL DE REQUERIMIENTOS
    // ============================================
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Req_Finalizados_Del_' . date('d-m-Y') . '.xls"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                xmlns:x="urn:schemas-microsoft-com:office:excel" 
                xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    
    echo '<table border="1" cellpadding="4" cellspacing="0">';
    
    // Encabezado institucional
    $columnas_req = ['CENTRO DE COSTO', 'RESPONSABLE', 'UBICACION DEL EQUIPO', 'TELEFONO', 'EQUIPO', 
                     'NUMERO DE INVENTARIO', 'DESCRIPCION', 'TIPO DE SERVICIO', 'TIPO DE ATENCION', 
                     'ESTATUS', 'NUMERO DE REQUERIMIENTO'];
    
    echo '<tr>';
    echo '<td colspan="' . count($columnas_req) . '" style="text-align:center; font-size:14px; font-weight:bold; font-family:Arial;">';
    echo 'INSTITUTO SALVADOREÑO DEL SEGURO SOCIAL<br>';
    echo 'HOSPITAL ZACAMIL<br>';
    echo 'REPORTE DE REQUERIMIENTOS DIARIOS<br>';
    echo 'FECHA: ' . date('d/m/Y');
    echo '</td>';
    echo '</tr>';
    
    // Encabezados
    echo '<tr style="background:#006699;">';
    foreach ($columnas_req as $col) {
        echo '<th style="color:white; font-weight:bold; text-align:center; font-size:9px; font-family:Arial; white-space:nowrap;">' . $col . '</th>';
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
        echo '<td colspan="' . count($columnas_req) . '" style="font-size:10px; font-family:Arial; text-align:center;">No hay requerimientos en este período</td>';
        echo '</tr>';
    }
    
    // Pie
    echo '<tr>';
    echo '<td colspan="' . count($columnas_req) . '" style="font-size:10px; font-weight:bold; font-family:Arial; padding:8px;">';
    echo 'TECNICO RESPONSABLE: ' . htmlspecialchars($tecnico) . ' TELEFONO: _________________';
    echo '</td>';
    echo '</tr>';
    
    // Nota
    echo '<tr>';
    echo '<td colspan="' . count($columnas_req) . '" style="background:#FFF8E1; font-size:8px; font-family:Arial; padding:6px;">';
    echo 'Reporte generado el ' . date('d/m/Y H:i:s') . ' - Sistema de Requerimientos SIR';
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit;
}

// ============================================
// SI ES REPORTE DE EQUIPOS (Inventario)
// ============================================
switch ($tipo) {
    case 'PC': $table = 't_inventpc'; break;
    case 'IMP': $table = 't_impresores'; break;
    case 'UPS': $table = 't_ups'; break;
    case 'OTROS': $table = 't_otros'; break;
    default: $table = '';
}

if (empty($table)) {
    die('Tipo de reporte no válido');
}

$sql = "SELECT * FROM $table";
if (!empty($filtro) && !empty($criterio)) {
    $sql .= " WHERE $filtro LIKE '%$criterio%'";
}
$sql .= " ORDER BY Nivel";

$equipos = $db->fetchAll($sql);

// ============================================
// GENERAR EXCEL DE EQUIPOS
// ============================================
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Reporte_' . strtolower($tipo) . '_' . date('Y-m-d') . '.xls"');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
            xmlns:x="urn:schemas-microsoft-com:office:excel" 
            xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';

echo '<table border="1" cellpadding="4" cellspacing="0">';

// Encabezado
$columnas_eq = ['Correlativo', 'Nivel', 'Tipo', 'Ubicacion', 'Responsable', 'Centro de Costo', 
                'Inventario', 'Activo', 'Marca', 'Modelo', 'Serie', 'Procesador', 'RAM', 'HDD', 
                'Sistema Operativo', 'Fecha Compra', 'Venc. Garantia', 'Estado'];

echo '<tr>';
echo '<td colspan="' . count($columnas_eq) . '" style="text-align:center; font-size:14px; font-weight:bold; font-family:Arial;">';
echo 'INSTITUTO SALVADOREÑO DEL SEGURO SOCIAL<br>';
echo 'HOSPITAL ZACAMIL<br>';
echo 'REPORTE DE EQUIPOS - ' . $tipo . '<br>';
echo 'FECHA: ' . date('d/m/Y');
echo '</td>';
echo '</tr>';

// Encabezados de columnas
echo '<tr style="background:#006699;">';
foreach ($columnas_eq as $col) {
    echo '<th style="color:white; font-weight:bold; text-align:center; font-size:9px; font-family:Arial; white-space:nowrap;">' . $col . '</th>';
}
echo '</tr>';

// Datos
$correlativo = 1;
foreach ($equipos as $row) {
    $estado = match($row['estadoEquipo'] ?? 0) {
        1 => 'ACTIVO',
        2 => 'MANTENIMIENTO',
        default => 'INACTIVO'
    };
    $estadoColor = match($estado) {
        'ACTIVO' => '#006600',
        'MANTENIMIENTO' => '#FF6600',
        default => '#CC0000'
    };
    
    echo '<tr>';
    echo '<td style="font-size:9px; font-family:Arial; text-align:center;">' . $correlativo++ . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['Nivel'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['tipo'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['ubicacion'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['responsable'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['cc'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['inventario'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['activo'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['marca'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['modelo'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['serie'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['procesador'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['ram'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['hdd'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['so'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['f_compra'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['venc_garantia'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial; font-weight:bold; color:' . $estadoColor . ';">' . $estado . '</td>';
    echo '</tr>';
}

echo '<tr>';
echo '<td colspan="' . count($columnas_eq) . '" style="font-size:10px; font-weight:bold; font-family:Arial; padding:8px;">';
echo 'TECNICO RESPONSABLE: ' . htmlspecialchars($tecnico) . ' TELEFONO: _________________';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="' . count($columnas_eq) . '" style="background:#FFF8E1; font-size:8px; font-family:Arial; padding:6px;">';
echo 'Reporte generado el ' . date('d/m/Y H:i:s') . ' - Sistema de Inventario SIR';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</body></html>';
exit;
?>