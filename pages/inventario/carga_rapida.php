<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

// ============================================
// CARGAR PHPSPREADSHEET
// ============================================
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// ============================================
// LIMPIAR DIAGNÓSTICO
// ============================================
if (isset($_GET['limpiar'])) {
    unset($_SESSION['diagnostico_excel']);
    unset($_SESSION['debug_info']);
    $_SESSION['carga_resultado'] = "🧹 Diagnóstico limpiado. Puedes subir un nuevo archivo.";
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ============================================
// EXPORTAR ERRORES A EXCEL
// ============================================
if (isset($_GET['exportar_errores']) && $_GET['exportar_errores'] == '1') {
    $diagnostico = $_SESSION['diagnostico_excel'] ?? null;
    
    if (!$diagnostico || empty($diagnostico['invalidos'])) {
        $_SESSION['carga_resultado'] = "⚠️ No hay errores para exportar.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Encabezados
    $headers = ['Fila', 'Inventario', 'Activo', 'CC', 'Ubicación', 'Tipo', 'Marca', 'Modelo', 'Serie', 'Errores'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    
    // Datos
    $row = 2;
    foreach ($diagnostico['invalidos'] as $equipo) {
        $sheet->setCellValue('A' . $row, $equipo['fila'] ?? '');
        $sheet->setCellValue('B' . $row, $equipo['inventario'] ?? '');
        $sheet->setCellValue('C' . $row, $equipo['activo'] ?? '');
        $sheet->setCellValue('D' . $row, $equipo['cc'] ?? '');
        $sheet->setCellValue('E' . $row, $equipo['ubicacion'] ?? '');
        $sheet->setCellValue('F' . $row, $equipo['tipo'] ?? '');
        $sheet->setCellValue('G' . $row, $equipo['marca'] ?? '');
        $sheet->setCellValue('H' . $row, $equipo['modelo'] ?? '');
        $sheet->setCellValue('I' . $row, $equipo['serie'] ?? '');
        $sheet->setCellValue('J' . $row, implode('; ', $equipo['errores'] ?? []));
        $row++;
    }
    
    // Estilo
    $sheet->getStyle('A1:J1')->getFont()->setBold(true);
    $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FF1a1a2e');
    $sheet->getStyle('A1:J1')->getFont()->setColor(new Color(Color::COLOR_WHITE));
    
    // Exportar
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="errores_importacion_' . date('Y-m-d_H-i-s') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ============================================
// PROCESAR EXCEL (XLSX / XLS / CSV)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['procesar_excel'])) {
    $file = $_FILES['archivo_excel'];
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['xlsx', 'xls', 'csv'];
    
    if (!in_array($extension, $extensiones_permitidas)) {
        $_SESSION['carga_resultado'] = "❌ Solo se permiten archivos Excel (XLSX, XLS) o CSV.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    try {
        $inputFileType = IOFactory::identify($file['tmp_name']);
        $reader = IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(true);
        
        if ($inputFileType === 'Csv') {
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(';');
        }
        
        $spreadsheet = $reader->load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $rows = array_filter($rows, function($row) {
            return !empty(array_filter($row, function($cell) {
                return !is_null($cell) && trim($cell) !== '';
            }));
        });
        
        $rows = array_values($rows);
        
        if (empty($rows)) {
            $_SESSION['carga_resultado'] = "❌ No se pudieron leer datos del archivo.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Guardar nombre del archivo para el log
        $_SESSION['nombre_archivo_importacion'] = $file['name'];
        
        $datos_procesados = procesarExcelOriginal($rows, $db);
        
        if (empty($datos_procesados['todos_los_datos'])) {
            $_SESSION['carga_resultado'] = "⚠️ No se encontraron datos válidos. Revisa el formato del archivo.";
            $_SESSION['debug_info'] = $datos_procesados['debug'] ?? [];
            $_SESSION['diagnostico_excel'] = $datos_procesados;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $_SESSION['diagnostico_excel'] = $datos_procesados;
        unset($_SESSION['debug_info']);
        
    } catch (Exception $e) {
        $_SESSION['carga_resultado'] = "❌ Error al leer el archivo: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================
// PROCESAR SELECCIÓN DE EQUIPOS
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['seleccionar_equipos'])) {
    $diagnostico = $_SESSION['diagnostico_excel'] ?? null;
    
    if (!$diagnostico) {
        $_SESSION['carga_resultado'] = "❌ No hay diagnóstico previo.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $equipos_seleccionados = $_POST['equipos_seleccionar'] ?? [];
    
    if (empty($equipos_seleccionados)) {
        $_SESSION['carga_resultado'] = "⚠️ Debes seleccionar al menos un equipo para importar.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $nuevos_todos = [];
    foreach ($diagnostico['todos_los_datos'] as $equipo) {
        if (in_array($equipo['inventario'], $equipos_seleccionados)) {
            $nuevos_todos[] = $equipo;
        }
    }
    
    if (empty($nuevos_todos)) {
        $_SESSION['carga_resultado'] = "⚠️ No se encontraron los equipos seleccionados.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $nuevos_validos = [];
    $nuevos_invalidos = [];
    foreach ($nuevos_todos as $equipo) {
        if ($equipo['es_valido'] && !$equipo['es_duplicado']) {
            $nuevos_validos[] = $equipo;
        } else {
            $nuevos_invalidos[] = $equipo;
        }
    }
    
    $diagnostico['todos_los_datos'] = $nuevos_todos;
    $diagnostico['validos'] = $nuevos_validos;
    $diagnostico['invalidos'] = $nuevos_invalidos;
    $diagnostico['total'] = count($nuevos_todos);
    
    $diagnostico['equipos_por_tipo'] = [
        'PC' => 0,
        'IMPRESORA' => 0,
        'UPS' => 0,
        'OTROS' => 0
    ];
    foreach ($nuevos_validos as $equipo) {
        $diagnostico['equipos_por_tipo'][$equipo['tipo']]++;
    }
    
    $diagnostico['grupos'] = [];
    foreach ($nuevos_todos as $equipo) {
        $grupo_key = ($equipo['ubicacion'] ?: 'SIN UBICACION') . '|' . $equipo['tipo'];
        if (!isset($diagnostico['grupos'][$grupo_key])) {
            $diagnostico['grupos'][$grupo_key] = [
                'ubicacion' => $equipo['ubicacion'] ?: 'SIN UBICACION',
                'tipo' => $equipo['tipo'],
                'equipos' => [],
                'total' => 0,
                'validos' => 0,
                'invalidos' => 0,
                'duplicados' => 0,
                'seleccionado' => true
            ];
        }
        $diagnostico['grupos'][$grupo_key]['equipos'][] = $equipo;
        $diagnostico['grupos'][$grupo_key]['total']++;
        if ($equipo['es_valido'] && !$equipo['es_duplicado']) {
            $diagnostico['grupos'][$grupo_key]['validos']++;
        } else {
            $diagnostico['grupos'][$grupo_key]['invalidos']++;
        }
    }
    
    $_SESSION['diagnostico_excel'] = $diagnostico;
    $_SESSION['carga_resultado'] = "✅ Seleccionados " . count($nuevos_validos) . " equipos válidos para importar.";
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================
// FUNCIÓN: PROCESAR EXCEL ORIGINAL
// ============================================
function procesarExcelOriginal($rows, $db) {
    $resultados = [
        'total' => 0,
        'validos' => [],
        'invalidos' => [],
        'duplicados' => [],
        'errores' => [],
        'equipos_por_tipo' => [
            'PC' => 0,
            'IMPRESORA' => 0,
            'UPS' => 0,
            'OTROS' => 0
        ],
        'equipos_por_cc' => [],
        'todos_los_datos' => [],
        'grupos' => [],
        'debug' => [
            'filas_recibidas' => count($rows)
        ]
    ];
    
    $tablas = ['t_inventpc', 't_impresores', 't_ups', 't_otros'];
    $inventarios_existentes = [];
    
    foreach ($tablas as $tabla) {
        try {
            $existentes = $db->fetchAll("SELECT inventario FROM $tabla");
            foreach ($existentes as $row) {
                $inventarios_existentes[] = $row['inventario'];
            }
        } catch (Exception $e) {
            // Ignorar
        }
    }
    
    $resultados['debug']['total_existentes'] = count($inventarios_existentes);
    
    $fila_inicio = 0;
    $header_fila = -1;
    
    for ($i = 0; $i < min(20, count($rows)); $i++) {
        if (empty($rows[$i])) continue;
        
        $fila_texto = '';
        foreach ($rows[$i] as $cell) {
            if (!is_null($cell)) {
                $fila_texto .= ' ' . trim($cell);
            }
        }
        $fila_lower = strtolower($fila_texto);
        
        if (strpos($fila_lower, 'número de inventario') !== false || 
            strpos($fila_lower, 'nro. inventario') !== false ||
            strpos($fila_lower, 'inventario') !== false) {
            $header_fila = $i;
            $fila_inicio = $i + 1;
            $resultados['debug']['header_fila'] = $i;
            break;
        }
    }
    
    if ($header_fila == -1) {
        for ($i = 0; $i < min(30, count($rows)); $i++) {
            if (empty($rows[$i])) continue;
            $cell = isset($rows[$i][2]) ? trim($rows[$i][2]) : '';
            if (is_numeric($cell) && strlen($cell) >= 4) {
                $fila_inicio = $i;
                $resultados['debug']['fila_inicio_por_numero'] = $i;
                break;
            }
        }
    }
    
    $resultados['debug']['fila_inicio'] = $fila_inicio;
    
    $cc_actual = '';
    $ubicacion_actual = '';
    $contador_filas = 0;
    
    for ($i = $fila_inicio; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        $row = array_map(function($cell) {
            return is_null($cell) ? '' : trim($cell);
        }, $row);
        
        if (empty(array_filter($row))) continue;
        if (count($row) < 4) continue;
        
        $cc_raw = isset($row[0]) ? trim($row[0]) : '';
        $ubicacion_raw = isset($row[1]) ? trim($row[1]) : '';
        $inventario_raw = isset($row[2]) ? trim($row[2]) : '';
        $activo_raw = isset($row[3]) ? trim($row[3]) : '';
        $nombre_equipo_raw = isset($row[4]) ? trim($row[4]) : '';
        $estatus = isset($row[5]) ? trim($row[5]) : '';
        $fecha_raw = isset($row[6]) ? trim($row[6]) : '';
        $marca_raw = isset($row[7]) ? trim($row[7]) : '';
        $modelo_raw = isset($row[8]) ? trim($row[8]) : '';
        $serie_raw = isset($row[9]) ? trim($row[9]) : '';
        
        if (is_numeric($fecha_raw) && $fecha_raw > 0) {
            try {
                $fecha_obj = Date::excelToDateTimeObject($fecha_raw);
                $fecha_raw = $fecha_obj->format('Y-m-d');
            } catch (Exception $e) {}
        }
        
        $cc_limpio = preg_replace('/[^0-9]/', '', $cc_raw);
        if (!empty($cc_limpio) && strlen($cc_limpio) >= 3) {
            $cc_actual = $cc_limpio;
        }
        
        if (!empty($ubicacion_raw) && $ubicacion_raw != '#' && $ubicacion_raw != 'S/M') {
            $ubicacion_actual = strtoupper(trim($ubicacion_raw));
        }
        
        $inventario_limpio = preg_replace('/[^0-9]/', '', $inventario_raw);
        if (empty($inventario_limpio) || strlen($inventario_limpio) < 4) {
            continue;
        }
        $inventario = $inventario_limpio;
        
        $activo_limpio = preg_replace('/[^0-9]/', '', $activo_raw);
        if (empty($activo_limpio) || strlen($activo_limpio) < 4) {
            $activo_final = 'ACT-' . $inventario;
        } else {
            $activo_final = $activo_limpio;
        }
        
        $contador_filas++;
        $resultados['total']++;
        
        $tipo = detectarTipoEquipo($nombre_equipo_raw);
        $resultados['equipos_por_tipo'][$tipo]++;
        
        $marca = $marca_raw;
        if (empty($marca) || $marca == '#' || $marca == 'S/M' || $marca == 'S/S') {
            $marca = extraerMarcaDeDescripcion($nombre_equipo_raw);
        }
        
        $modelo = $modelo_raw;
        if (empty($modelo) || $modelo == '#' || $modelo == 'S/M' || $modelo == 'S/S') {
            $modelo = extraerModeloDeDescripcion($nombre_equipo_raw, $marca);
        }
        
        $serie = $serie_raw;
        if (empty($serie) || $serie == '#' || $serie == 'S/S' || $serie == 'S/M') {
            for ($j = 10; $j <= 12; $j++) {
                if (isset($row[$j]) && !empty(trim($row[$j])) && trim($row[$j]) != '#' && trim($row[$j]) != 'S/S') {
                    $serie = trim($row[$j]);
                    break;
                }
            }
            if (empty($serie) || $serie == '#') {
                $serie = 'SN-' . $inventario;
            }
        }
        
        if (!empty($fecha_raw) && $fecha_raw != '#' && $fecha_raw != 'S/M') {
            $fecha = preg_replace('/\s.*$/', '', $fecha_raw);
        } else {
            $fecha = date('Y-m-d');
        }
        
        $marca = strtoupper(trim($marca));
        $modelo = strtoupper(trim($modelo));
        $serie = strtoupper(trim($serie));
        $nombre_equipo = strtoupper(trim($nombre_equipo_raw));
        
        $errores = [];
        if (empty($inventario)) $errores[] = "Inventario vacío";
        if (empty($marca) || $marca == '#') $errores[] = "Marca no encontrada";
        if (empty($modelo) || $modelo == '#') $errores[] = "Modelo no encontrado";
        if (empty($serie) || $serie == '#') $errores[] = "Serie no encontrada";
        
        $es_duplicado = in_array($inventario, $inventarios_existentes);
        if ($es_duplicado) {
            $errores[] = "⚠️ DUPLICADO - Inventario $inventario ya existe";
            $resultados['duplicados'][] = $inventario;
        }
        
        $equipo = [
            'fila' => $i + 1,
            'inventario' => $inventario,
            'activo' => $activo_final,
            'activo_original' => $activo_raw,
            'cc' => $cc_actual ?: '000',
            'ubicacion' => $ubicacion_actual ?: 'SIN UBICACION',
            'tipo' => $tipo,
            'nombre_equipo' => $nombre_equipo,
            'marca' => $marca,
            'modelo' => $modelo,
            'serie' => $serie,
            'fecha' => $fecha,
            'estatus' => $estatus,
            'errores' => $errores,
            'es_valido' => empty($errores) && !$es_duplicado,
            'es_duplicado' => $es_duplicado
        ];
        
        $resultados['todos_los_datos'][] = $equipo;
        
        if ($equipo['es_valido']) {
            $resultados['validos'][] = $equipo;
        } else {
            $resultados['invalidos'][] = $equipo;
        }
        
        $ubicacion_final = $ubicacion_actual ?: 'SIN UBICACION';
        $grupo_key = $ubicacion_final . '|' . $tipo;
        
        if (!isset($resultados['grupos'][$grupo_key])) {
            $resultados['grupos'][$grupo_key] = [
                'ubicacion' => $ubicacion_final,
                'tipo' => $tipo,
                'equipos' => [],
                'total' => 0,
                'validos' => 0,
                'invalidos' => 0,
                'duplicados' => 0,
                'seleccionado' => false
            ];
        }
        $resultados['grupos'][$grupo_key]['equipos'][] = $equipo;
        $resultados['grupos'][$grupo_key]['total']++;
        if ($equipo['es_valido']) {
            $resultados['grupos'][$grupo_key]['validos']++;
        } elseif ($equipo['es_duplicado']) {
            $resultados['grupos'][$grupo_key]['duplicados']++;
        } else {
            $resultados['grupos'][$grupo_key]['invalidos']++;
        }
    }
    
    ksort($resultados['grupos']);
    
    $resultados['debug']['total_filas_procesadas'] = $contador_filas;
    $resultados['debug']['total_validos'] = count($resultados['validos']);
    $resultados['debug']['total_invalidos'] = count($resultados['invalidos']);
    $resultados['debug']['total_duplicados'] = count($resultados['duplicados']);
    $resultados['filas_procesadas'] = $contador_filas;
    
    return $resultados;
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

function detectarTipoEquipo($nombre) {
    $nombre = strtoupper($nombre);
    if (empty($nombre)) return 'OTROS';
    
    $pc = ['COMPUTADORA', 'COMPUTADOR', 'CPU', 'PC', 'LAPTOP', 'PORTATIL', 'NOTEBOOK', 
           'ELITEDESK', 'PRODESK', 'OPTIPLEX', 'THINKPAD', 'LATITUDE', 'PRECISION',
           'HP PRO', 'DELL', 'LENOVO', 'MAC', 'IMAC', 'MACBOOK', 'MONITOR', 'PANTALLA'];
    
    $impresora = ['IMPRESOR', 'IMPRESORA', 'PRINTER', 'TICKET', 'MATRICIAL', 'TERMICA',
                  'EPSON', 'HP LASER', 'HP DESKJET', 'CANON', 'BROTHER', 'SCANNER',
                  'LECTOR', 'CODIGO', 'BARRA'];
    
    $ups = ['UPS', 'NO BREAK', 'REGULADOR', 'ESTABILIZADOR', 'ORBITEC', 'ABLEREX', 'APC',
            'POWER SUPPLY'];
    
    $otros = ['ARCHIVADOR', 'ESCRITORIO', 'SILLA', 'ESTANTE', 'MESA', 'CARRO', 'BANCO', 
              'LAMPARA', 'TELEFONO', 'AIRE', 'VENTILADOR', 'REFRIGERADORA',
              'MUEBLE', 'PIZARRA', 'RACK', 'EXTINTOR', 'BOCINA', 'CARTELERA',
              'GRADILLA', 'CANAPE', 'CAMILLA', 'BIOMBO', 'GINECOLOGICA'];
    
    foreach ($otros as $palabra) {
        if (strpos($nombre, $palabra) !== false) return 'OTROS';
    }
    foreach ($pc as $palabra) {
        if (strpos($nombre, $palabra) !== false) return 'PC';
    }
    foreach ($impresora as $palabra) {
        if (strpos($nombre, $palabra) !== false) return 'IMPRESORA';
    }
    foreach ($ups as $palabra) {
        if (strpos($nombre, $palabra) !== false) return 'UPS';
    }
    
    return 'OTROS';
}

function extraerMarcaDeDescripcion($descripcion) {
    $descripcion = trim($descripcion);
    if (empty($descripcion)) return 'GENERICO';
    
    $descripcion_upper = strtoupper($descripcion);
    
    $marcas = ['HP', 'DELL', 'LENOVO', 'EPSON', 'APC', 'ORBITEC', 'ABLEREX', 
               'BENQ', 'TALLY', 'SUNLUX', 'SIEMENS', 'COMFORTSTAR', 'HUAWEI', 
               'AOC', 'GODEX', 'SYMBOL', 'WALTER', 'KEMP', 'CONTEC', 'STURDY',
               'CANON', 'BROTHER', 'KYOCERA', 'XEROX', 'OKI', 'RICOH', 'HISENSE'];
    
    foreach ($marcas as $marca) {
        if (strpos($descripcion_upper, $marca) !== false) {
            return $marca;
        }
    }
    
    $palabras = explode(' ', $descripcion_upper);
    foreach ($palabras as $palabra) {
        $palabra = trim($palabra);
        if (strlen($palabra) >= 3 && !in_array($palabra, ['S/M', 'S/S', 'EL', 'LA', 'LOS', 'LAS', 'DE', 'DEL'])) {
            return $palabra;
        }
    }
    
    return 'GENERICO';
}

function extraerModeloDeDescripcion($descripcion, $marca) {
    $descripcion = trim($descripcion);
    if (empty($descripcion)) return 'MODELO-SIN-DESCRIPCION';
    
    $descripcion_upper = strtoupper($descripcion);
    $marca_upper = strtoupper(trim($marca));
    
    if (empty($marca_upper) || $marca_upper == 'GENERICO' || $marca_upper == '#') {
        $palabras = explode(' ', $descripcion_upper);
        $modelo = implode(' ', array_slice($palabras, 0, 3));
        return substr($modelo, 0, 50);
    }
    
    $modelo = str_ireplace($marca_upper, '', $descripcion_upper);
    $modelo = trim($modelo);
    $modelo = preg_replace('/[^A-Za-z0-9\-\s]/', '', $modelo);
    
    if (empty($modelo)) {
        $palabras = explode(' ', $descripcion_upper);
        $comunes = ['S/M', 'S/S', 'S/SR', 'EL', 'LA', 'LOS', 'LAS', 'DE', 'DEL'];
        foreach ($palabras as $palabra) {
            $palabra = trim($palabra);
            if (!empty($palabra) && strlen($palabra) > 2 && !in_array($palabra, $comunes) && $palabra != $marca_upper) {
                $modelo = $palabra;
                break;
            }
        }
        if (empty($modelo)) {
            $modelo = substr($descripcion_upper, 0, 30);
        }
    }
    
    return substr($modelo, 0, 50);
}

// ============================================
// PROCESAR CONFIRMACIÓN CON LOG
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_importacion'])) {
    $diagnostico = $_SESSION['diagnostico_excel'] ?? null;
    
    if (!$diagnostico || empty($diagnostico['validos'])) {
        $_SESSION['carga_resultado'] = "❌ No hay equipos válidos para importar.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // ============================================
    // APLICAR EDICIONES EN LÍNEA
    // ============================================
    $cambios_aplicados = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'edit_') === 0) {
            $parts = explode('_', $key);
            if (count($parts) >= 4) {
                $rowId = $parts[1] . '_' . $parts[2];
                $campo = $parts[3];
                $cambios_aplicados[$rowId][$campo] = trim($value);
            }
        }
    }
    
    // Aplicar cambios al array de válidos
    if (!empty($cambios_aplicados)) {
        foreach ($diagnostico['validos'] as $idx => &$equipo) {
            $row_id_equipo = 'row_' . $idx;
            if (isset($cambios_aplicados[$row_id_equipo])) {
                foreach ($cambios_aplicados[$row_id_equipo] as $campo => $valor) {
                    if (in_array($campo, ['marca', 'modelo', 'serie'])) {
                        $equipo[$campo] = $valor;
                    }
                }
            }
        }
    }
    
    $tipo_mapeo = [
        'PC' => 't_inventpc',
        'IMPRESORA' => 't_impresores',
        'UPS' => 't_ups',
        'OTROS' => 't_otros'
    ];
    
    $registrados = 0;
    $errores = [];
    $detalle_log = [];
    $hoy = date('Y-m-d H:i:s');
    
    // Iniciar log
    $nombre_archivo = $_SESSION['nombre_archivo_importacion'] ?? 'manual';
    $log_data = [
        'fecha_importacion' => $hoy,
        'usuario' => Session::get('user_id') ?? 'ADMIN',
        'nombre_archivo' => $nombre_archivo,
        'total_equipos' => count($diagnostico['validos']),
        'importados' => 0,
        'errores' => 0,
        'detalle' => ''
    ];
    
    foreach ($diagnostico['validos'] as $equipo) {
        $tabla = $tipo_mapeo[$equipo['tipo']] ?? 't_otros';
        
        $existe = $db->fetchOne("SELECT inventario FROM $tabla WHERE inventario = ?", [$equipo['inventario']]);
        if ($existe) {
            $errores[] = "Inventario {$equipo['inventario']} ya existe (omitido)";
            $detalle_log[] = [
                'inventario' => $equipo['inventario'],
                'tipo' => $equipo['tipo'],
                'accion' => 'omitido',
                'mensaje' => 'Inventario duplicado'
            ];
            continue;
        }
        
        $data = [
            'inventario'    => $equipo['inventario'],
            'activo'        => $equipo['activo'],
            'cc'            => $equipo['cc'] ?: '000',
            'responsable'   => $_POST['responsable'] ?? 'SISTEMA',
            'ubicacion'     => $equipo['ubicacion'] ?: 'SIN UBICACION',
            'telefono'      => $_POST['telefono'] ?? '00000000',
            'tipo'          => $equipo['tipo'],
            'marca'         => $equipo['marca'],
            'modelo'        => $equipo['modelo'],
            'serie'         => $equipo['serie'],
            'f_compra'      => $equipo['fecha'] ?? date('Y-m-d'),
            'venc_garantia' => date('Y-m-d', strtotime('+1 year', strtotime($equipo['fecha'] ?? date('Y-m-d')))),
            'estadoEquipo'  => 1,
            'nivel'         => $_POST['nivel'] ?? 'NIVEL 1',
            'user_dom'      => $_POST['user_dom'] ?? 'admin',
            'insertuser'    => Session::get('user_id') ?? 'ADMIN',
            'insertdate'    => date('Y-m-d')
        ];
        
        if ($equipo['tipo'] == 'PC') {
            $data['procesador'] = $_POST['procesador'] ?? '';
            $data['ram'] = $_POST['ram'] ?? '';
            $data['hdd'] = $_POST['hdd'] ?? '';
            $data['so'] = $_POST['so'] ?? '';
        }
        
        if ($equipo['tipo'] == 'UPS') {
            $data['capacidadSalida'] = $_POST['capacidad'] ?? '';
            $data['numTomas'] = intval($_POST['tomas'] ?? 0);
        }
        
        try {
            $db->insert($tabla, $data);
            $registrados++;
            $detalle_log[] = [
                'inventario' => $equipo['inventario'],
                'tipo' => $equipo['tipo'],
                'accion' => 'importado',
                'mensaje' => 'Importado correctamente'
            ];
        } catch (Exception $e) {
            $errores[] = "Error en {$equipo['inventario']}: " . $e->getMessage();
            $detalle_log[] = [
                'inventario' => $equipo['inventario'],
                'tipo' => $equipo['tipo'],
                'accion' => 'error',
                'mensaje' => $e->getMessage()
            ];
        }
    }
    
    // Guardar log principal
    $log_data['importados'] = $registrados;
    $log_data['errores'] = count($errores);
    $log_data['detalle'] = json_encode($detalle_log);
    
    try {
        // Crear tabla de logs si no existe
        $db->query("CREATE TABLE IF NOT EXISTS t_log_importaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha_importacion DATETIME NOT NULL,
            usuario VARCHAR(100) NOT NULL,
            nombre_archivo VARCHAR(255),
            total_equipos INT DEFAULT 0,
            importados INT DEFAULT 0,
            errores INT DEFAULT 0,
            detalle TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->query("CREATE TABLE IF NOT EXISTS t_log_importacion_detalle (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_id INT NOT NULL,
            inventario VARCHAR(20) NOT NULL,
            tipo VARCHAR(20),
            accion VARCHAR(20),
            mensaje TEXT,
            FOREIGN KEY (log_id) REFERENCES t_log_importaciones(id) ON DELETE CASCADE
        )");
        
        $log_id = $db->insert('t_log_importaciones', $log_data);
        
        foreach ($detalle_log as $detalle) {
            $detalle['log_id'] = $log_id;
            $db->insert('t_log_importacion_detalle', $detalle);
        }
    } catch (Exception $e) {
        error_log("Error guardando log de importación: " . $e->getMessage());
    }
    
    unset($_SESSION['nombre_archivo_importacion']);
    
    $mensaje = "✅ Se importaron <strong>$registrados</strong> equipos.";
    if (!empty($errores)) {
        $mensaje .= "<br>⚠️ <strong>" . count($errores) . "</strong> errores:<br><ul>";
        foreach (array_slice($errores, 0, 10) as $error) {
            $mensaje .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        if (count($errores) > 10) {
            $mensaje .= "<li>... y " . (count($errores) - 10) . " más</li>";
        }
        $mensaje .= "</ul>";
    }
    
    $_SESSION['carga_resultado'] = $mensaje;
    unset($_SESSION['diagnostico_excel']);
    unset($_SESSION['debug_info']);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================
// RECUPERAR DATOS DE SESIÓN
// ============================================
$resultado = $_SESSION['carga_resultado'] ?? null;
unset($_SESSION['carga_resultado']);

$diagnostico = $_SESSION['diagnostico_excel'] ?? null;
$debug_info = $_SESSION['debug_info'] ?? null;
unset($_SESSION['debug_info']);

$total_grupos = 0;
$total_duplicados = 0;
$total_validos = 0;
if ($diagnostico && isset($diagnostico['grupos'])) {
    $total_grupos = count($diagnostico['grupos']);
    $total_duplicados = $diagnostico['debug']['total_duplicados'] ?? 0;
    $total_validos = count($diagnostico['validos']);
}

$tipos_presentes = [];
if ($diagnostico && isset($diagnostico['equipos_por_tipo'])) {
    foreach ($diagnostico['equipos_por_tipo'] as $tipo => $cantidad) {
        if ($cantidad > 0) {
            $tipos_presentes[] = $tipo;
        }
    }
}
$hay_pc = in_array('PC', $tipos_presentes);
$hay_ups = in_array('UPS', $tipos_presentes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Rápida - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/cyber-style.css">
    <style>
        .cyber-card {
            background: rgba(10, 14, 26, 0.85);
            border: 1px solid rgba(0, 240, 255, 0.12);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .cyber-card-header {
            font-family: 'Orbitron', monospace;
            color: #00f0ff;
            padding: 6px 16px;
            border-bottom: 1px solid rgba(0, 240, 255, 0.08);
            font-size: 0.7rem;
            letter-spacing: 1px;
        }
        .cyber-card-body {
            padding: 12px 16px !important;
        }
        .carga-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 6px 10px;
        }
        .carga-grid label {
            font-size: 0.55rem;
            font-weight: 600;
            color: rgba(200, 214, 229, 0.4);
            display: block;
            margin-bottom: 1px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .carga-grid input, .carga-grid select {
            width: 100%;
            padding: 3px 6px;
            background: rgba(0, 240, 255, 0.03);
            border: 1px solid rgba(0, 240, 255, 0.1);
            border-radius: 4px;
            font-size: 0.75rem;
            color: #c8d6e5;
        }
        .carga-grid input:focus, .carga-grid select:focus {
            border-color: #00f0ff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 240, 255, 0.1);
        }
        .carga-grid select option {
            background: #0a0e1a;
            color: #c8d6e5;
        }
        .campos-especificos {
            display: none;
            border-top: 1px solid rgba(0, 240, 255, 0.06);
            padding-top: 8px;
            margin-top: 8px;
        }
        .campos-especificos.visible {
            display: block;
        }
        .badge-tipo-mini {
            font-size: 0.5rem;
            padding: 1px 8px;
            border-radius: 10px;
            display: inline-block;
        }
        .badge-tipo-mini.pc { background: rgba(0, 240, 255, 0.1); color: #00f0ff; }
        .badge-tipo-mini.ups { background: rgba(255, 215, 0, 0.1); color: #ffd93d; }
        
        .carga-btn {
            background: linear-gradient(135deg, #00f0ff, #0066ff);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 6px;
            padding: 6px 20px;
            transition: all 0.3s ease;
            font-family: 'Orbitron', monospace;
            font-size: 0.6rem;
            letter-spacing: 1px;
            cursor: pointer;
        }
        .carga-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(0, 240, 255, 0.2);
            color: #fff;
        }
        .carga-btn-success {
            background: linear-gradient(135deg, #00e676, #00c853);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 6px;
            padding: 8px 24px;
            transition: all 0.3s ease;
            font-family: 'Orbitron', monospace;
            font-size: 0.6rem;
            letter-spacing: 1px;
            cursor: pointer;
        }
        .carga-btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(0, 230, 118, 0.2);
            color: #fff;
        }
        .carga-btn-success:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .btn-limpiar {
            background: rgba(255, 45, 85, 0.05);
            border: 1px solid rgba(255, 45, 85, 0.1);
            color: #ff2d55;
            padding: 4px 14px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-limpiar:hover {
            background: rgba(255, 45, 85, 0.1);
            border-color: rgba(255, 45, 85, 0.2);
            color: #ff2d55;
        }
        
        .badge-tipo {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 0.5rem;
            font-weight: 600;
        }
        .badge-tipo.pc { background: rgba(0, 240, 255, 0.1); color: #00f0ff; }
        .badge-tipo.impresora { background: rgba(0, 230, 118, 0.1); color: #00e676; }
        .badge-tipo.ups { background: rgba(255, 215, 0, 0.1); color: #ffd93d; }
        .badge-tipo.otros { background: rgba(200, 214, 229, 0.05); color: rgba(200, 214, 229, 0.5); }
        .badge-valid { background: rgba(0, 230, 118, 0.08); color: #00e676; padding: 1px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 600; }
        .badge-invalid { background: rgba(255, 45, 85, 0.08); color: #ff2d55; padding: 1px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 600; }
        .badge-duplicado { background: rgba(255, 215, 0, 0.08); color: #ffd93d; padding: 1px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 600; }
        .badge-activo { background: rgba(255, 215, 0, 0.08); color: #ffd93d; padding: 1px 6px; border-radius: 8px; font-size: 0.5rem; font-weight: 600; }
        .badge-seleccionado {
            background: rgba(0, 230, 118, 0.08);
            color: #00e676;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.45rem;
            font-weight: 600;
        }
        
        .carga-tip { font-size: 0.65rem; color: rgba(200, 214, 229, 0.3); background: rgba(0, 240, 255, 0.02); padding: 4px 10px; border-radius: 4px; border: 1px solid rgba(0, 240, 255, 0.04); margin-bottom: 6px; }
        .carga-tip code { background: rgba(0,0,0,0.3); color: #00f0ff; padding: 1px 4px; border-radius: 3px; font-size: 0.6rem; }
        .carga-result { padding: 8px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.8rem; }
        .carga-result.success { background: rgba(0, 230, 118, 0.06); border: 1px solid rgba(0, 230, 118, 0.12); color: #00e676; }
        .carga-result.error { background: rgba(255, 45, 85, 0.06); border: 1px solid rgba(255, 45, 85, 0.12); color: #ff2d55; }
        .carga-result.info { background: rgba(255, 215, 0, 0.06); border: 1px solid rgba(255, 215, 0, 0.12); color: #ffd93d; }
        .section-divider { border-top: 1px solid rgba(0, 240, 255, 0.06); margin: 10px 0; }
        
        .diagnostico-container {
            background: rgba(0, 240, 255, 0.02);
            border-radius: 6px;
            padding: 10px;
            border: 1px solid rgba(0, 240, 255, 0.05);
        }
        .diagnostico-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
            gap: 6px;
            margin-bottom: 8px;
        }
        .diagnostico-stats .stat {
            text-align: center;
            padding: 4px;
            border-radius: 4px;
            background: rgba(0, 240, 255, 0.02);
            border: 1px solid rgba(0, 240, 255, 0.04);
        }
        .diagnostico-stats .stat .number {
            font-family: 'Orbitron', monospace;
            font-size: 1rem;
            font-weight: 700;
        }
        .diagnostico-stats .stat .label {
            font-size: 0.45rem;
            color: rgba(200, 214, 229, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-valid .number { color: #00e676; }
        .stat-invalid .number { color: #ff2d55; }
        .stat-total .number { color: #00f0ff; }
        .stat-grupos .number { color: #ffd93d; }
        .stat-duplicados .number { color: #ffd93d; }
        
        .diagnostico-table {
            width: 100%;
            font-size: 0.6rem;
            border-collapse: collapse;
        }
        .diagnostico-table th {
            text-align: left;
            color: rgba(200, 214, 229, 0.3);
            font-weight: 600;
            font-size: 0.45rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 6px;
            border-bottom: 1px solid rgba(0, 240, 255, 0.06);
            position: sticky;
            top: 0;
            background: rgba(10, 14, 26, 0.95);
            z-index: 1;
        }
        .diagnostico-table td {
            padding: 2px 6px;
            border-bottom: 1px solid rgba(0, 240, 255, 0.03);
            color: #c8d6e5;
            font-size: 0.55rem;
        }
        .diagnostico-table .row-valid td { border-left: 2px solid rgba(0, 230, 118, 0.3); }
        .diagnostico-table .row-duplicado td { border-left: 2px solid rgba(255, 215, 0, 0.3); background: rgba(255, 215, 0, 0.02); }
        .diagnostico-table .row-invalid td { border-left: 2px solid rgba(255, 45, 85, 0.3); }
        .diagnostico-scroll { max-height: 300px; overflow-y: auto; border-radius: 4px; }
        .diagnostico-scroll::-webkit-scrollbar { width: 4px; }
        .diagnostico-scroll::-webkit-scrollbar-track { background: rgba(0, 240, 255, 0.02); border-radius: 4px; }
        .diagnostico-scroll::-webkit-scrollbar-thumb { background: rgba(0, 240, 255, 0.15); border-radius: 4px; }
        
        .table-count { color: rgba(200, 214, 229, 0.2); font-size: 0.45rem; }
        .seleccion-todos { color: rgba(200, 214, 229, 0.2); font-size: 0.5rem; cursor: pointer; }
        .seleccion-todos:hover { color: rgba(200, 214, 229, 0.4); }
        
        .error-list {
            background: rgba(255, 45, 85, 0.02);
            border: 1px solid rgba(255, 45, 85, 0.06);
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 8px;
            max-height: 150px;
            overflow-y: auto;
        }
        .error-list .error-item {
            font-size: 0.55rem;
            padding: 2px 0;
            color: rgba(200, 214, 229, 0.6);
            border-bottom: 1px solid rgba(255, 45, 85, 0.03);
        }
        .error-list .error-item .badge-duplicado { font-size: 0.45rem; }
        .error-list .error-item .badge-invalid { font-size: 0.45rem; }
        
        /* Estilos para selección de equipos */
        .equipo-checkbox {
            accent-color: #00e676;
            width: 14px;
            height: 14px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .equipo-checkbox:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .grupo-header {
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .grupo-header:hover {
            background: rgba(0, 240, 255, 0.03);
        }
        .grupo-contenido {
            padding-left: 20px;
            margin-top: 4px;
            border-left: 1px solid rgba(0, 240, 255, 0.04);
            display: none;
        }
        .grupo-contenido.abierto {
            display: block;
        }
        .equipo-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 1px 0;
            border-bottom: 1px solid rgba(0, 240, 255, 0.02);
        }
        .equipo-item .equipo-info {
            font-size: 0.55rem;
            color: #c8d6e5;
        }
        .equipo-item .equipo-info .inv {
            color: #00f0ff;
            font-weight: 600;
        }
        .equipo-item .equipo-info .detalle {
            color: rgba(200, 214, 229, 0.3);
            font-size: 0.45rem;
        }
        .equipo-item .equipo-info .error-text {
            color: rgba(200, 214, 229, 0.15);
            font-size: 0.4rem;
            margin-left: 4px;
        }
        .equipo-item .estado-icon {
            font-size: 0.45rem;
            width: 16px;
            text-align: center;
        }
        .equipo-item .estado-icon.valido { color: #00e676; }
        .equipo-item .estado-icon.duplicado { color: #ffd93d; }
        .equipo-item .estado-icon.invalido { color: #ff2d55; }
        
        /* Estilos para edición en línea */
        .campo-editable {
            background: rgba(0, 240, 255, 0.03);
            border: 1px solid rgba(0, 240, 255, 0.06);
            border-radius: 3px;
            color: #c8d6e5;
            font-size: 0.55rem;
            padding: 1px 4px;
            width: 100%;
            transition: all 0.3s ease;
        }
        .campo-editable:hover {
            border-color: rgba(0, 240, 255, 0.15);
            background: rgba(0, 240, 255, 0.06);
        }
        .campo-editable:focus {
            border-color: #00f0ff;
            outline: none;
            background: rgba(0, 240, 255, 0.08);
            box-shadow: 0 0 0 2px rgba(0, 240, 255, 0.05);
        }
        .campo-editable.cambiado {
            border-color: #ffd93d;
            background: rgba(255, 215, 0, 0.05);
        }
        
        .btn-exportar-errores {
            background: rgba(255, 215, 0, 0.05);
            border: 1px solid rgba(255, 215, 0, 0.1);
            color: #ffd93d;
            padding: 3px 12px;
            border-radius: 4px;
            font-size: 0.55rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-exportar-errores:hover {
            background: rgba(255, 215, 0, 0.1);
            border-color: rgba(255, 215, 0, 0.2);
            color: #ffd93d;
        }
        
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .text-center { text-align: center; }
        .d-flex { display: flex; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .flex-wrap { flex-wrap: wrap; }
        .align-items-center { align-items: center; }
        .justify-content-between { justify-content: space-between; }
    </style>
</head>
<body style="background: #0a0e1a; min-height: 100vh;">

    <nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(6, 10, 21, 0.95); border-bottom: 1px solid rgba(0, 240, 255, 0.06); backdrop-filter: blur(10px); padding: 4px 16px;">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php" style="font-family: 'Orbitron', monospace; color: #00f0ff; font-weight: 900; font-size: 1.1rem;">
                <i class="bi bi-boxes"></i> SIR
            </a>
            <a class="btn btn-outline-light" href="buscar.php" style="border-radius: 30px; border-color: rgba(255,255,255,0.05); font-size: 0.6rem; padding: 3px 12px; color: rgba(200,214,229,0.3);">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </nav>

    <div class="container mt-2">
        <div class="row justify-content-center">
            <div class="col-lg-12">

                <div class="text-center mb-2">
                    <h1 style="font-family: 'Orbitron', monospace; color: #00f0ff; font-weight: 700; font-size: 1.3rem; text-shadow: 0 0 30px rgba(0, 240, 255, 0.1);">
                        <i class="bi bi-lightning-charge"></i> CARGA RÁPIDA
                    </h1>
                    <p style="color: rgba(200, 214, 229, 0.12); font-family: 'Rajdhani', sans-serif; letter-spacing: 2px; font-size: 0.55rem; text-transform: uppercase;">
                        Sube tu Excel, edita, selecciona y importa
                    </p>
                </div>

                <?php if ($resultado): ?>
                    <div class="carga-result <?= strpos($resultado, '✅') !== false ? 'success' : (strpos($resultado, '⚠️') !== false ? 'info' : 'error') ?>">
                        <?= $resultado ?>
                    </div>
                <?php endif; ?>

                <div class="cyber-card">
                    <div class="cyber-card-header">
                        <i class="bi bi-upload"></i> CARGA MASIVA
                        <?php if ($diagnostico): ?>
                        <span style="color:rgba(200,214,229,0.15); font-size:0.5rem; margin-left:10px;">
                            <?= count($diagnostico['validos']) ?> válidos · 
                            <?= count($diagnostico['invalidos']) ?> inválidos
                            <?php if ($total_duplicados > 0): ?>
                            · <span style="color:#ffd93d;"><?= $total_duplicados ?> duplicados</span>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="cyber-card-body">

                        <?php if (!$diagnostico || empty($diagnostico['todos_los_datos'])): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div style="background: rgba(0, 240, 255, 0.02); border-radius: 6px; padding: 10px; border: 1px solid rgba(0, 240, 255, 0.04);">
                                <div class="carga-tip">
                                    <i class="bi bi-info-circle"></i> Formatos soportados:
                                    <code>.xlsx</code> <code>.xls</code> <code>.csv</code>
                                    <br>Columnas esperadas:
                                    <code>CC, Nombre CC, Inventario, Activo, Nombre Equipo, Estatus, Fecha, Marca, Modelo, Serie</code>
                                </div>
                                <div class="row g-1">
                                    <div class="col-md-8">
                                        <input type="file" class="form-control form-control-sm" name="archivo_excel" accept=".xlsx,.xls,.csv" required style="background:rgba(0,240,255,0.02); border:1px solid rgba(0,240,255,0.1); color:#c8d6e5; font-size:0.75rem; padding:4px 8px;">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" name="procesar_excel" class="carga-btn" style="padding:4px 16px; width:100%; font-size:0.6rem;">
                                            <i class="bi bi-search"></i> PROCESAR
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php if ($diagnostico && !empty($diagnostico['todos_los_datos'])): ?>
                        
                        <div class="section-divider"></div>

                        <!-- BOTÓN LIMPIAR -->
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:8px;">
                            <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                <?php if (!empty($diagnostico['invalidos'])): ?>
                                <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?exportar_errores=1" class="btn-exportar-errores">
                                    <i class="bi bi-download"></i> Exportar errores
                                    <span style="background:rgba(255,215,0,0.1); padding:0 4px; border-radius:3px; font-size:0.45rem;">
                                        <?= count($diagnostico['invalidos']) ?>
                                    </span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?limpiar=1" class="btn-limpiar" onclick="return confirm('¿Limpiar diagnóstico? Los datos se perderán.');">
                                <i class="bi bi-eraser"></i> Limpiar todo
                            </a>
                        </div>

                        <!-- ============================================ -->
                        <!-- PASO 2: SELECCIONAR EQUIPOS PARA IMPORTAR -->
                        <!-- ============================================ -->
                        <?php if (!empty($diagnostico['grupos'])): ?>
                        <div style="background: rgba(255, 215, 0, 0.02); border-radius: 6px; padding: 10px; border: 1px solid rgba(255, 215, 0, 0.06); margin-bottom: 10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:6px;">
                                <div>
                                    <span style="color: rgba(200, 214, 229, 0.3); font-size: 0.5rem; letter-spacing: 1px; text-transform: uppercase;">
                                        <i class="bi bi-check2-square" style="color: #00e676;"></i> SELECCIONAR EQUIPOS PARA IMPORTAR
                                    </span>
                                    <span style="color:rgba(200,214,229,0.12); font-size:0.45rem; margin-left:6px;">
                                        (<?= $total_grupos ?> grupos · <?= $total_validos ?> equipos válidos)
                                        <?php if ($total_duplicados > 0): ?>
                                        · <span style="color:#ffd93d;"><?= $total_duplicados ?> duplicados</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <span class="seleccion-todos" onclick="seleccionarTodosEquipos(true)" style="color:#00e676;">
                                        ✅ Seleccionar todos los válidos
                                    </span>
                                    <span class="seleccion-todos" onclick="seleccionarTodosEquipos(false)" style="color:rgba(200,214,229,0.3);">
                                        ❌ Deseleccionar todos
                                    </span>
                                </div>
                            </div>
                            
                            <form method="POST" id="formSeleccionarEquipos">
                                <div style="max-height:400px; overflow-y:auto; border-radius:4px; background:rgba(0,240,255,0.01); padding:4px;">
                                    
                                    <?php foreach ($diagnostico['grupos'] as $key => $grupo): 
                                        $tipo_clase = strtolower($grupo['tipo']);
                                        $tiene_validos = ($grupo['validos'] ?? 0) > 0;
                                        $total_validos_grupo = $grupo['validos'] ?? 0;
                                        $total_duplicados_grupo = $grupo['duplicados'] ?? 0;
                                        $grupo_id = md5($key);
                                    ?>
                                    <div style="margin-bottom:6px; border:1px solid rgba(0,240,255,0.04); border-radius:4px; padding:4px 8px; background:rgba(0,240,255,0.01);">
                                        
                                        <!-- CABECERA DEL GRUPO -->
                                        <div class="grupo-header d-flex justify-content-between align-items-center" onclick="toggleGrupo('<?= $grupo_id ?>')">
                                            <div class="d-flex align-items-center gap-1">
                                                <span id="icon_<?= $grupo_id ?>" style="color:rgba(200,214,229,0.2); font-size:0.5rem;">▶</span>
                                                <span style="color: #00f0ff; font-weight: 600; font-size: 0.65rem;">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($grupo['ubicacion']) ?>
                                                </span>
                                                <span class="badge-tipo <?= $tipo_clase ?>"><?= $grupo['tipo'] ?></span>
                                                <?php if ($tiene_validos): ?>
                                                    <span style="color:#00e676; font-size:0.45rem;">(<?= $total_validos_grupo ?> válidos)</span>
                                                <?php endif; ?>
                                                <?php if ($total_duplicados_grupo > 0): ?>
                                                    <span style="color:#ffd93d; font-size:0.45rem;">⚠️ <?= $total_duplicados_grupo ?> duplicados</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span style="color:rgba(200,214,229,0.12); font-size:0.4rem;"><?= $grupo['total'] ?> equipos</span>
                                                <?php if ($tiene_validos): ?>
                                                    <input type="checkbox" class="grupo-selector" data-grupo="<?= $grupo_id ?>" 
                                                           onchange="seleccionarGrupo('<?= $grupo_id ?>', this.checked)" checked>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- LISTA DE EQUIPOS DEL GRUPO (colapsable) -->
                                        <div id="grupo_<?= $grupo_id ?>" class="grupo-contenido">
                                            <?php foreach ($grupo['equipos'] as $equipo):
                                                $es_valido = $equipo['es_valido'] && !$equipo['es_duplicado'];
                                                $es_duplicado = $equipo['es_duplicado'] ?? false;
                                                $estado_clase = $es_valido ? 'valido' : ($es_duplicado ? 'duplicado' : 'invalido');
                                                $estado_icono = $es_valido ? '✅' : ($es_duplicado ? '⚠️' : '❌');
                                            ?>
                                            <div class="equipo-item">
                                                <?php if ($es_valido): ?>
                                                    <input type="checkbox" name="equipos_seleccionar[]" 
                                                           value="<?= htmlspecialchars($equipo['inventario']) ?>" 
                                                           class="equipo-checkbox grupo-<?= $grupo_id ?>" checked>
                                                    <span class="estado-icon valido">✅</span>
                                                <?php else: ?>
                                                    <span style="width:14px;"></span>
                                                    <span class="estado-icon <?= $estado_clase ?>"><?= $estado_icono ?></span>
                                                <?php endif; ?>
                                                
                                                <span class="equipo-info" style="<?= !$es_valido ? 'opacity:0.4;' : '' ?>">
                                                    <span class="inv"><?= htmlspecialchars($equipo['inventario'] ?? '') ?></span>
                                                    <span class="detalle">| <?= htmlspecialchars($equipo['marca'] ?? '') ?> <?= htmlspecialchars($equipo['modelo'] ?? '') ?></span>
                                                    <?php if ($es_valido): ?>
                                                        <span class="detalle">| <?= htmlspecialchars($equipo['serie'] ?? '') ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!$es_valido): ?>
                                                        <span class="error-text">
                                                            <?php foreach ($equipo['errores'] as $error): ?>
                                                                <?php if (strpos($error, 'DUPLICADO') !== false): ?>
                                                                    (⚠️ duplicado)
                                                                <?php elseif (strpos($error, 'Marca') !== false): ?>
                                                                    (sin marca)
                                                                <?php elseif (strpos($error, 'Modelo') !== false): ?>
                                                                    (sin modelo)
                                                                <?php elseif (strpos($error, 'Serie') !== false): ?>
                                                                    (sin serie)
                                                                <?php else: ?>
                                                                    (<?= $error ?>)
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="d-flex gap-1 flex-wrap mt-1">
                                    <button type="submit" name="seleccionar_equipos" class="carga-btn-success" id="btnSeleccionarEquipos">
                                        <i class="bi bi-check-lg"></i> IMPORTAR EQUIPOS SELECCIONADOS
                                    </button>
                                    <span style="color:rgba(200,214,229,0.08); font-size:0.5rem; align-self:center;">
                                        Solo los equipos marcados con ✅ se importarán
                                    </span>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- ============================================ -->
                        <!-- PASO 3: DATOS CONSTANTES -->
                        <!-- ============================================ -->
                        <?php 
                        $hay_seleccionados = false;
                        if ($diagnostico && isset($diagnostico['validos']) && count($diagnostico['validos']) > 0) {
                            $hay_seleccionados = true;
                        }
                        ?>
                        
                        <?php if ($hay_seleccionados || !empty($diagnostico['validos'])): ?>
                        <form method="POST" id="formImportar">
                            <div style="background: rgba(255, 215, 0, 0.02); border-radius: 6px; padding: 10px; border: 1px solid rgba(255, 215, 0, 0.06); margin-bottom: 10px;">
                                <div style="color: rgba(200, 214, 229, 0.3); font-size: 0.5rem; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px;">
                                    <i class="bi bi-gear" style="color: #ffd93d;"></i> DATOS CONSTANTES
                                    <span style="color:rgba(200,214,229,0.1); font-size:0.45rem; font-weight:normal; text-transform:none;">
                                        (se aplican a todos los equipos)
                                    </span>
                                </div>
                                
                                <div class="carga-grid">
                                    <div>
                                        <label>RESPONSABLE</label>
                                        <input type="text" name="responsable" value="SISTEMA">
                                    </div>
                                    <div>
                                        <label>TELÉFONO</label>
                                        <input type="text" name="telefono" value="00000000">
                                    </div>
                                    <div>
                                        <label>NIVEL</label>
                                        <input type="text" name="nivel" value="NIVEL 1">
                                    </div>
                                    <div>
                                        <label>USUARIO DOM.</label>
                                        <input type="text" name="user_dom" value="admin">
                                    </div>
                                </div>
                                
                                <?php if ($hay_pc): ?>
                                <div class="campos-especificos visible">
                                    <div style="color: rgba(200, 214, 229, 0.2); font-size: 0.5rem; letter-spacing: 0.5px; margin-bottom: 4px;">
                                        <span class="badge-tipo-mini pc">💻 PC</span>
                                        <span style="color:rgba(200,214,229,0.1); font-size:0.45rem;">(solo para equipos PC)</span>
                                    </div>
                                    <div class="carga-grid">
                                        <div>
                                            <label>PROCESADOR</label>
                                            <input type="text" name="procesador" placeholder="I7-12700">
                                        </div>
                                        <div>
                                            <label>RAM</label>
                                            <input type="text" name="ram" placeholder="16GB">
                                        </div>
                                        <div>
                                            <label>HDD/SSD</label>
                                            <input type="text" name="hdd" placeholder="1TB">
                                        </div>
                                        <div>
                                            <label>S.O.</label>
                                            <input type="text" name="so" placeholder="WIN 11">
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hay_ups): ?>
                                <div class="campos-especificos visible">
                                    <div style="color: rgba(200, 214, 229, 0.2); font-size: 0.5rem; letter-spacing: 0.5px; margin-bottom: 4px;">
                                        <span class="badge-tipo-mini ups">⚡ UPS</span>
                                        <span style="color:rgba(200,214,229,0.1); font-size:0.45rem;">(solo para equipos UPS)</span>
                                    </div>
                                    <div class="carga-grid">
                                        <div>
                                            <label>CAPACIDAD</label>
                                            <input type="text" name="capacidad" placeholder="1500VA">
                                        </div>
                                        <div>
                                            <label>N° TOMAS</label>
                                            <input type="text" name="tomas" placeholder="8">
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- ============================================ -->
                            <!-- PASO 4: PREVISUALIZACIÓN CON EDICIÓN EN LÍNEA -->
                            <!-- ============================================ -->
                            <div class="diagnostico-container">
                                <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                                    <div style="color: #00e676; font-family:'Orbitron',monospace; font-size:0.6rem; letter-spacing:1px;">
                                        <i class="bi bi-pencil" style="color:#ffd93d;"></i> EDICIÓN EN LÍNEA
                                        <span style="color:rgba(200,214,229,0.1); font-size:0.45rem; font-weight:normal;">
                                            (click en los campos para editar)
                                        </span>
                                    </div>
                                    <span style="color:rgba(200,214,229,0.15); font-size:0.5rem;">
                                        <?= count($diagnostico['validos']) ?> equipos válidos
                                    </span>
                                </div>

                                <!-- Tabla de VÁLIDOS con edición en línea -->
                                <?php if (!empty($diagnostico['validos'])): ?>
                                <div style="background:rgba(0,230,118,0.02); border-radius:4px; padding:2px 6px; border:1px solid rgba(0,230,118,0.08);">
                                    <div style="color:rgba(200,214,229,0.15); font-size:0.45rem; letter-spacing:0.5px; margin-bottom:2px;">
                                        <i class="bi bi-table"></i> PREVISUALIZACIÓN
                                        <span class="table-count">(<?= count($diagnostico['validos']) ?> equipos)</span>
                                    </div>
                                    <div class="diagnostico-scroll">
                                        <table class="diagnostico-table" id="tablaEditable">
                                            <thead>
                                                <tr>
                                                    <th style="width:30px;">#</th>
                                                    <th>Inventario</th>
                                                    <th>Activo</th>
                                                    <th>CC</th>
                                                    <th>Ubicación</th>
                                                    <th>Tipo</th>
                                                    <th style="min-width:80px;">Marca</th>
                                                    <th style="min-width:100px;">Modelo</th>
                                                    <th style="min-width:120px;">Serie</th>
                                                    <th style="width:50px;">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($diagnostico['validos'] as $index => $equipo): 
                                                    $tipo_clase = strtolower($equipo['tipo'] ?? 'otros');
                                                    $row_id = 'row_' . $index;
                                                ?>
                                                <tr class="row-valid" id="<?= $row_id ?>">
                                                    <td><?= $equipo['fila'] ?></td>
                                                    <td><strong style="color:#00f0ff;"><?= htmlspecialchars($equipo['inventario'] ?? '') ?></strong></td>
                                                    <td><span class="badge-activo"><?= htmlspecialchars($equipo['activo'] ?? '') ?></span></td>
                                                    <td><?= htmlspecialchars($equipo['cc'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($equipo['ubicacion'] ?? '') ?></td>
                                                    <td><span class="badge-tipo <?= $tipo_clase ?>"><?= $equipo['tipo'] ?? 'OTROS' ?></span></td>
                                                    <td>
                                                        <input type="text" class="campo-editable" data-row="<?= $row_id ?>" data-field="marca" 
                                                               value="<?= htmlspecialchars($equipo['marca'] ?? '') ?>" 
                                                               onchange="actualizarEquipo('<?= $row_id ?>', 'marca', this.value)"
                                                               title="Editar marca">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="campo-editable" data-row="<?= $row_id ?>" data-field="modelo" 
                                                               value="<?= htmlspecialchars($equipo['modelo'] ?? '') ?>" 
                                                               onchange="actualizarEquipo('<?= $row_id ?>', 'modelo', this.value)"
                                                               title="Editar modelo">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="campo-editable" data-row="<?= $row_id ?>" data-field="serie" 
                                                               value="<?= htmlspecialchars($equipo['serie'] ?? '') ?>" 
                                                               onchange="actualizarEquipo('<?= $row_id ?>', 'serie', this.value)"
                                                               title="Editar serie">
                                                    </td>
                                                    <td><span class="badge-valid">✅ Válido</span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div style="color:rgba(200,214,229,0.06); font-size:0.4rem; padding:2px 4px; text-align:right;">
                                        <i class="bi bi-info-circle"></i> Los cambios se guardan automáticamente al importar
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- ============================================ -->
                                <!-- ERRORES (INVÁLIDOS + DUPLICADOS) -->
                                <!-- ============================================ -->
                                <?php if (!empty($diagnostico['invalidos'])): ?>
                                <div style="margin-top:8px;">
                                    <div style="color:rgba(200,214,229,0.15); font-size:0.45rem; letter-spacing:0.5px; margin-bottom:4px;">
                                        <i class="bi bi-exclamation-triangle" style="color:#ff2d55;"></i> EQUIPOS OMITIDOS (NO SE IMPORTARÁN)
                                        <span style="color:rgba(200,214,229,0.1); font-size:0.4rem;">(<?= count($diagnostico['invalidos']) ?> equipos)</span>
                                        <?php if (!empty($diagnostico['invalidos'])): ?>
                                        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?exportar_errores=1" class="btn-exportar-errores" style="font-size:0.45rem; padding:1px 8px; margin-left:4px;">
                                            <i class="bi bi-download"></i> Exportar
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="error-list">
                                        <?php foreach ($diagnostico['invalidos'] as $equipo): 
                                            $es_duplicado = $equipo['es_duplicado'] ?? false;
                                            $badge_clase = $es_duplicado ? 'duplicado' : 'invalid';
                                        ?>
                                        <div class="error-item">
                                            <strong style="color:<?= $es_duplicado ? '#ffd93d' : '#ff2d55' ?>;">
                                                Fila <?= $equipo['fila'] ?>
                                            </strong> 
                                            (Inv: <?= htmlspecialchars($equipo['inventario'] ?? 'N/A') ?>):
                                            <?php foreach ($equipo['errores'] as $error): ?>
                                                <span class="badge-<?= $badge_clase ?>" style="margin-left:2px; font-size:0.45rem;"><?= $error ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Botón importar -->
                            <div style="background:rgba(0,230,118,0.02); border-radius:4px; padding:8px; border:1px solid rgba(0,230,118,0.06); margin-top:8px;">
                                <input type="hidden" name="confirmar_importacion" value="1">
                                <button type="submit" class="carga-btn-success" <?= empty($diagnostico['validos']) ? 'disabled' : '' ?> style="width:100%;">
                                    <i class="bi bi-check-lg"></i> 
                                    IMPORTAR <?= count($diagnostico['validos']) ?> EQUIPOS VÁLIDOS
                                </button>
                                <div style="text-align:center; margin-top:2px; color:rgba(200,214,229,0.08); font-size:0.45rem;">
                                    <?= count($diagnostico['invalidos']) ?> equipos con errores serán omitidos
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php endif; ?>

                    </div>
                </div>

                <div class="text-center mt-2" style="border-top: 1px solid rgba(0, 240, 255, 0.03); padding-top: 6px;">
                    <span style="color: rgba(200, 214, 229, 0.05); font-family: 'Rajdhani', sans-serif; font-size: 0.45rem; letter-spacing: 3px;">
                        <i class="bi bi-cpu"></i> SIR v3.0
                    </span>
                </div>

            </div>
        </div>
    </div>

    <script>
    // ============================================
    // FUNCIONES PARA SELECCIÓN POR EQUIPO
    // ============================================

    // Alternar visibilidad de un grupo
    function toggleGrupo(grupoId) {
        var container = document.getElementById('grupo_' + grupoId);
        var icon = document.getElementById('icon_' + grupoId);
        if (container) {
            if (container.classList.contains('abierto')) {
                container.classList.remove('abierto');
                if (icon) icon.textContent = '▶';
            } else {
                container.classList.add('abierto');
                if (icon) icon.textContent = '▼';
            }
        }
    }

    // Seleccionar/deseleccionar todos los equipos de un grupo
    function seleccionarGrupo(grupoId, seleccionar) {
        var checkboxes = document.querySelectorAll('.grupo-' + grupoId);
        checkboxes.forEach(function(cb) {
            cb.checked = seleccionar;
        });
        actualizarContador();
    }

    // Seleccionar/deseleccionar TODOS los equipos válidos
    function seleccionarTodosEquipos(seleccionar) {
        var checkboxes = document.querySelectorAll('.equipo-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = seleccionar;
        });
        // Actualizar checkboxes de grupo
        document.querySelectorAll('.grupo-selector').forEach(function(cb) {
            var grupoId = cb.getAttribute('data-grupo');
            var checkboxesGrupo = document.querySelectorAll('.grupo-' + grupoId);
            var todosSeleccionados = true;
            checkboxesGrupo.forEach(function(c) {
                if (!c.checked) todosSeleccionados = false;
            });
            cb.checked = todosSeleccionados && checkboxesGrupo.length > 0;
        });
        actualizarContador();
    }

    // Contador de equipos seleccionados
    function actualizarContador() {
        var seleccionados = document.querySelectorAll('.equipo-checkbox:checked').length;
        var total = document.querySelectorAll('.equipo-checkbox').length;
        var btn = document.getElementById('btnSeleccionarEquipos');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-check-lg"></i> IMPORTAR ' + seleccionados + ' EQUIPOS SELECCIONADOS';
            btn.disabled = (seleccionados === 0);
        }
    }

    // Actualizar contador cuando cambia un checkbox
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('equipo-checkbox')) {
            actualizarContador();
            // Actualizar checkbox del grupo
            var grupoId = e.target.className.split(' ').find(function(c) { return c.startsWith('grupo-'); });
            if (grupoId) {
                var id = grupoId.replace('grupo-', '');
                var checkboxesGrupo = document.querySelectorAll('.grupo-' + id);
                var selector = document.querySelector('.grupo-selector[data-grupo="' + id + '"]');
                if (selector && checkboxesGrupo.length > 0) {
                    var todosSeleccionados = true;
                    checkboxesGrupo.forEach(function(c) {
                        if (!c.checked) todosSeleccionados = false;
                    });
                    selector.checked = todosSeleccionados;
                }
            }
        }
    });

    // ============================================
    // EDICIÓN EN LÍNEA
    // ============================================
    var cambiosPendientes = {};

    function actualizarEquipo(rowId, campo, valor) {
        if (!cambiosPendientes[rowId]) {
            cambiosPendientes[rowId] = {};
        }
        cambiosPendientes[rowId][campo] = valor;
        
        // Marcar el input como cambiado
        var input = document.querySelector('#tablaEditable input[data-row="' + rowId + '"][data-field="' + campo + '"]');
        if (input) {
            input.classList.add('cambiado');
            setTimeout(function() {
                input.classList.remove('cambiado');
            }, 1500);
        }
    }

    // Inicializar contador al cargar
    document.addEventListener('DOMContentLoaded', function() {
        actualizarContador();
    });
    </script>

</body>
</html>