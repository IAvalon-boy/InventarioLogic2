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
$tipo = $_GET['tipo'] ?? 'todos';
$filtro = $_GET['filtro'] ?? '';
$criterio = $_GET['criterio'] ?? '';
$tecnico = Session::get('user_name') ?? 'TECNICO';

// ============================================
// CONSULTAR TODOS LOS EQUIPOS
// ============================================
$equipos = [];

// PCs
$pc = $db->fetchAll("SELECT * FROM t_inventpc");
foreach ($pc as $row) {
    $row['tipo_equipo'] = 'PC';
    $equipos[] = $row;
}

// Impresoras
$imp = $db->fetchAll("SELECT * FROM t_impresores");
foreach ($imp as $row) {
    $row['tipo_equipo'] = 'IMPRESORA';
    $equipos[] = $row;
}

// UPS
$ups = $db->fetchAll("SELECT * FROM t_ups");
foreach ($ups as $row) {
    $row['tipo_equipo'] = 'UPS';
    $equipos[] = $row;
}

// Otros
$otros = $db->fetchAll("SELECT * FROM t_otros");
foreach ($otros as $row) {
    $row['tipo_equipo'] = 'OTROS';
    $equipos[] = $row;
}

// Aplicar filtros
if (!empty($filtro) && !empty($criterio)) {
    $criterio_lower = strtolower($criterio);
    $equipos = array_filter($equipos, function($e) use ($filtro, $criterio_lower) {
        $valor = strtolower($e[$filtro] ?? '');
        return strpos($valor, $criterio_lower) !== false;
    });
    $equipos = array_values($equipos);
}

// ============================================
// GENERAR EXCEL
// ============================================
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Inventario_Completo_Al_' . date('d-m-Y') . '.xls"');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
            xmlns:x="urn:schemas-microsoft-com:office:excel" 
            xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';

echo '<table border="1" cellpadding="4" cellspacing="0">';

// ============================================
// ENCABEZADO INSTITUCIONAL
// ============================================
$columnas = ['Correlativo', 'Nivel', 'Tipo', 'Ubicacion', 'Nombre del Usuario', 'Centro de Costo', 
             'Numero de Inventario', 'Marca', 'Modelo', 'Serie', 'Procesador', 'RAM', 'HDD', 'CD / DVD', 
             'Sistema Operativo', 'Version de Office', 'Sistemas Institucionales Instalados', 
             'Otros Software (Utilitarios)', 'Antivirus', 'Nombre del Equipo', 'IP del Equipo', 
             'Fecha de Compra', 'Fecha de Vencimiento de Garantia', 'Estado del Equipo'];

echo '<tr>';
echo '<td colspan="' . count($columnas) . '" style="text-align:center; font-size:14px; font-weight:bold; font-family:Arial;">';
echo 'INSTITUTO SALVADOREÑO DEL SEGURO SOCIAL<br>';
echo 'HOSPITAL ZACAMIL<br>';
echo 'INVENTARIO COMPLETO DE EQUIPO INFORMATICO<br>';
echo 'FECHA: ' . date('d/m/Y');
echo '</td>';
echo '</tr>';

// ============================================
// ENCABEZADOS DE COLUMNAS
// ============================================
echo '<tr style="background:#006699;">';
foreach ($columnas as $col) {
    echo '<th style="color:white; font-weight:bold; text-align:center; font-size:9px; font-family:Arial; white-space:nowrap;">' . $col . '</th>';
}
echo '</tr>';

// ============================================
// DATOS
// ============================================
$correlativo = 1;
foreach ($equipos as $row) {
    // Determinar estado
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
    
    // Nivel
    $nivel = $row['Nivel'] ?? 'N/A';
    
    echo '<tr>';
    echo '<td style="font-size:9px; font-family:Arial; text-align:center;">' . $correlativo++ . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($nivel) . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['tipo'] ?? $row['tipo_equipo'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['ubicacion'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['responsable'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['cc'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['inventario'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['marca'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['modelo'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['serie'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['procesador'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['ram'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['hdd'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['cdrom'] ?? 'N/A') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['so'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['office'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['sistemasIsss'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['otrosSistemas'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['antivirus'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['nombreEquipo'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['ip'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['f_compra'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial;">' . htmlspecialchars($row['venc_garantia'] ?? '') . '</td>';
    echo '<td style="font-size:9px; font-family:Arial; font-weight:bold; color:' . $estadoColor . ';">' . $estado . '</td>';
    echo '</tr>';
}

// ============================================
// PIE DE PÁGINA
// ============================================
echo '<tr>';
echo '<td colspan="' . count($columnas) . '" style="font-size:10px; font-weight:bold; font-family:Arial; padding:8px;">';
echo 'TECNICO RESPONSABLE: ' . htmlspecialchars($tecnico) . ' TELEFONO: _________________';
echo '</td>';
echo '</tr>';

// ============================================
// NOTA FINAL
// ============================================
echo '<tr>';
echo '<td colspan="' . count($columnas) . '" style="background:#FFF8E1; font-size:8px; font-family:Arial; padding:6px;">';
echo 'Reporte generado el ' . date('d/m/Y H:i:s') . ' - Sistema de Inventario SIR';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</body></html>';
exit;
?>