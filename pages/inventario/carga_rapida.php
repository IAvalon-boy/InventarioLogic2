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
// NUEVO: DESHACER IMPORTACIÓN
// ============================================
if (isset($_GET['deshacer']) && is_numeric($_GET['deshacer'])) {
    $log_id = intval($_GET['deshacer']);
    
    try {
        $log = $db->fetchOne("SELECT * FROM t_log_importaciones WHERE id = ?", [$log_id]);
        if (!$log) {
            $_SESSION['carga_resultado'] = "❌ Log de importación no encontrado.";
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        
        $detalles = $db->fetchAll(
            "SELECT * FROM t_log_importacion_detalle WHERE log_id = ? AND accion = 'importado'",
            [$log_id]
        );
        
        if (empty($detalles)) {
            $_SESSION['carga_resultado'] = "⚠️ No hay equipos para deshacer en esta importación.";
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        
        $eliminados = 0;
        $errores = [];
        
        $tipo_mapeo = [
            'PC' => 't_inventpc',
            'IMPRESORA' => 't_impresores',
            'UPS' => 't_ups',
            'OTROS' => 't_otros'
        ];
        
        foreach ($detalles as $detalle) {
            $tipo = $detalle['tipo'] ?? 'OTROS';
            $tabla = $tipo_mapeo[$tipo] ?? 't_otros';
            $inventario = $detalle['inventario'];
            
            try {
                $existe = $db->fetchOne("SELECT inventario FROM $tabla WHERE inventario = ?", [$inventario]);
                if ($existe) {
                    $db->query("DELETE FROM $tabla WHERE inventario = ?", [$inventario]);
                    $eliminados++;
                    $db->query(
                        "UPDATE t_log_importacion_detalle SET accion = 'deshecho', mensaje = ? WHERE id = ?",
                        ['Equipo eliminado al deshacer importación', $detalle['id']]
                    );
                } else {
                    $errores[] = "Inventario $inventario no encontrado en $tabla (ya fue eliminado)";
                }
            } catch (Exception $e) {
                $errores[] = "Error eliminando $inventario: " . $e->getMessage();
            }
        }
        
        $db->query(
            "UPDATE t_log_importaciones SET deshecho = 1, fecha_deshecho = NOW() WHERE id = ?",
            [$log_id]
        );
        
        $mensaje = "✅ Importación #$log_id deshecha. Se eliminaron <strong>$eliminados</strong> equipos.";
        if (!empty($errores)) {
            $mensaje .= "<br>⚠️ " . count($errores) . " errores:<br><ul>";
            foreach (array_slice($errores, 0, 10) as $error) {
                $mensaje .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            if (count($errores) > 10) {
                $mensaje .= "<li>... y " . (count($errores) - 10) . " más</li>";
            }
            $mensaje .= "</ul>";
        }
        
        $_SESSION['carga_resultado'] = $mensaje;
        
    } catch (Exception $e) {
        $_SESSION['carga_resultado'] = "❌ Error al deshacer importación: " . $e->getMessage();
    }
    
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ============================================
// NUEVO: VER LOGS DE IMPORTACIÓN
// ============================================
if (isset($_GET['ver_logs'])) {
    $_SESSION['ver_logs'] = true;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (isset($_GET['ocultar_logs'])) {
    unset($_SESSION['ver_logs']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ============================================
// LIMPIAR DIAGNÓSTICO
// ============================================
if (isset($_GET['limpiar'])) {
    unset($_SESSION['diagnostico_excel']);
    unset($_SESSION['debug_info']);
    unset($_SESSION['equipos_seleccionados']);
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
    
    $headers = ['Fila', 'Inventario', 'Activo', 'CC', 'Ubicación', 'Tipo', 'Marca', 'Modelo', 'Serie', 'Errores'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    
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
    
    $sheet->getStyle('A1:J1')->getFont()->setBold(true);
    $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FF1a237e');
    $sheet->getStyle('A1:J1')->getFont()->setColor(new Color(Color::COLOR_WHITE));
    
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
        unset($_SESSION['equipos_seleccionados']);
        
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
    
    // Guardar los inventarios seleccionados en sesión
    $_SESSION['equipos_seleccionados'] = $equipos_seleccionados;
    
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
// PROCESAR CONFIRMACIÓN CON LOG (SOLO SELECCIONADOS)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_importacion'])) {
    $diagnostico = $_SESSION['diagnostico_excel'] ?? null;
    $equipos_seleccionados = $_SESSION['equipos_seleccionados'] ?? [];
    
    if (!$diagnostico) {
        $_SESSION['carga_resultado'] = "❌ No hay diagnóstico previo.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // FILTRAR SOLO LOS EQUIPOS SELECCIONADOS
    $equipos_a_importar = [];
    if (!empty($equipos_seleccionados)) {
        foreach ($diagnostico['validos'] as $equipo) {
            if (in_array($equipo['inventario'], $equipos_seleccionados)) {
                $equipos_a_importar[] = $equipo;
            }
        }
    } else {
        // Si no hay selección, usar todos los válidos (por seguridad)
        $equipos_a_importar = $diagnostico['validos'];
    }
    
    if (empty($equipos_a_importar)) {
        $_SESSION['carga_resultado'] = "❌ No hay equipos seleccionados para importar. Por favor, selecciona al menos un equipo.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // APLICAR EDICIONES EN LÍNEA
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
    
    if (!empty($cambios_aplicados)) {
        foreach ($equipos_a_importar as $idx => &$equipo) {
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
    
    $nombre_archivo = $_SESSION['nombre_archivo_importacion'] ?? 'manual';
    $log_data = [
        'fecha_importacion' => $hoy,
        'usuario' => Session::get('user_id') ?? 'ADMIN',
        'nombre_archivo' => $nombre_archivo,
        'total_equipos' => count($equipos_a_importar),
        'importados' => 0,
        'errores' => 0,
        'detalle' => '',
        'deshecho' => 0
    ];
    
    foreach ($equipos_a_importar as $equipo) {
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
    
    $log_data['importados'] = $registrados;
    $log_data['errores'] = count($errores);
    $log_data['detalle'] = json_encode($detalle_log);
    
    try {
        $db->query("CREATE TABLE IF NOT EXISTS t_log_importaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha_importacion DATETIME NOT NULL,
            usuario VARCHAR(100) NOT NULL,
            nombre_archivo VARCHAR(255),
            total_equipos INT DEFAULT 0,
            importados INT DEFAULT 0,
            errores INT DEFAULT 0,
            detalle TEXT,
            deshecho INT DEFAULT 0,
            fecha_deshecho DATETIME NULL,
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
    unset($_SESSION['equipos_seleccionados']);
    
    $mensaje = "✅ Se importaron <strong>$registrados</strong> equipos de <strong>" . count($equipos_a_importar) . "</strong> seleccionados.";
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
    
    if (isset($log_id)) {
        $mensaje .= "<br><a href='" . $_SERVER['PHP_SELF'] . "?deshacer=$log_id' class='btn btn-danger btn-sm' onclick='return confirm(\"¿Estás seguro de deshacer la importación #$log_id? Se eliminarán todos los equipos importados.\")'>
            <i class='bi bi-arrow-counterclockwise'></i> Deshacer importación
        </a>";
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

$mostrar_logs = $_SESSION['ver_logs'] ?? false;

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

// ============================================
// OBTENER LOGS DE IMPORTACIONES
// ============================================
$logs_importacion = [];
if ($mostrar_logs) {
    try {
        $logs_importacion = $db->fetchAll(
            "SELECT * FROM t_log_importaciones ORDER BY id DESC LIMIT 50"
        );
        foreach ($logs_importacion as &$log) {
            $detalles = $db->fetchAll(
                "SELECT COUNT(*) as total FROM t_log_importacion_detalle WHERE log_id = ? AND accion = 'importado'",
                [$log['id']]
            );
            $log['equipos_importados'] = $detalles[0]['total'] ?? 0;
        }
    } catch (Exception $e) {
        $logs_importacion = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Rápida - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* ============================================ */
        /* ESTILOS EXISTENTES (se mantienen igual)       */
        /* ============================================ */
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1a237e, #0d1757);
            padding: 8px 16px;
        }
        .navbar-custom .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff;
        }
        .navbar-custom .navbar-brand i {
            margin-right: 8px;
        }
        .navbar-custom .btn-outline-light {
            border-color: rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            padding: 4px 14px;
            border-radius: 30px;
        }
        .navbar-custom .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-custom .card-header {
            background: linear-gradient(135deg, #1a237e, #0d1757);
            color: #fff;
            padding: 12px 20px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        .card-custom .card-header i {
            margin-right: 8px;
        }
        .card-custom .card-body {
            background: #fff;
            padding: 24px 28px !important;
        }
        .form-label-custom {
            color: #555;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .form-control-custom {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control-custom:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .carga-btn {
            background: linear-gradient(135deg, #1a237e, #0d1757);
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .carga-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);
            color: #fff;
        }
        .carga-btn-success {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 8px;
            padding: 12px 24px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        .carga-btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            color: #fff;
        }
        .carga-btn-success:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-limpiar {
            background: transparent;
            border: 1px solid #e0c0c0;
            color: #c62828;
            border-radius: 6px;
            padding: 6px 16px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-limpiar:hover {
            background: #ffebee;
            border-color: #c62828;
            color: #b71c1c;
        }
        .btn-exportar-errores {
            background: transparent;
            border: 1px solid #e0c8a0;
            color: #e65100;
            border-radius: 6px;
            padding: 6px 16px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-exportar-errores:hover {
            background: #fff8e1;
            border-color: #e65100;
            color: #bf360c;
        }
        .btn-outline-custom {
            background: transparent;
            border: 1px solid #ddd;
            color: #777;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.85rem;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-outline-custom:hover {
            background: #f5f5f5;
            border-color: #bbb;
            color: #333;
        }
        .btn-ver-logs {
            background: transparent;
            border: 1px solid #b0c4de;
            color: #1a237e;
            border-radius: 6px;
            padding: 6px 16px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-ver-logs:hover {
            background: #e8eaf6;
            border-color: #1a237e;
            color: #0d1757;
        }
        .btn-deshecho {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .btn-deshecho.eliminado {
            background: #ffebee;
            color: #c62828;
            border-color: #ef9a9a;
        }
        .badge-custom {
            background: #e8eaf6;
            color: #1a237e;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-danger {
            background: #ffebee;
            color: #c62828;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-warning {
            background: #fff8e1;
            color: #f57f17;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-tipo {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 0.5rem;
            font-weight: 600;
        }
        .badge-tipo.pc { background: #e3f2fd; color: #0d47a1; }
        .badge-tipo.impresora { background: #fff3e0; color: #e65100; }
        .badge-tipo.ups { background: #fff8e1; color: #f57f17; }
        .badge-tipo.otros { background: #f5f5f5; color: #888; }
        .badge-valid { background: #e8f5e9; color: #2e7d32; padding: 1px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 600; }
        .badge-invalid { background: #ffebee; color: #c62828; padding: 1px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 600; }
        .badge-duplicado { background: #fff8e1; color: #f57f17; padding: 1px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 600; }
        .badge-activo { background: #fff8e1; color: #f57f17; padding: 1px 6px; border-radius: 8px; font-size: 0.5rem; font-weight: 600; }
        .carga-tip {
            font-size: 0.75rem;
            color: #999;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #eee;
            margin-bottom: 10px;
        }
        .carga-tip code {
            background: #e8eaf6;
            color: #1a237e;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
        }
        .carga-result {
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.85rem;
        }
        .carga-result.success {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
        }
        .carga-result.error {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
        }
        .carga-result.info {
            background: #fff8e1;
            border: 1px solid #ffecb3;
            color: #e65100;
        }
        .section-divider {
            border-top: 1px solid #eee;
            margin: 14px 0;
        }
        .diagnostico-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #eee;
        }
        .diagnostico-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 6px;
            margin-bottom: 10px;
        }
        .diagnostico-stats .stat {
            text-align: center;
            padding: 6px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #eee;
        }
        .diagnostico-stats .stat .number {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .diagnostico-stats .stat .label {
            font-size: 0.5rem;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-valid .number { color: #2e7d32; }
        .stat-invalid .number { color: #c62828; }
        .stat-total .number { color: #1a237e; }
        .stat-grupos .number { color: #f57f17; }
        .stat-duplicados .number { color: #f57f17; }
        .diagnostico-table {
            width: 100%;
            font-size: 0.7rem;
            border-collapse: collapse;
        }
        .diagnostico-table th {
            text-align: left;
            color: #999;
            font-weight: 600;
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 8px;
            border-bottom: 2px solid #eee;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }
        .diagnostico-table td {
            padding: 3px 8px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
            font-size: 0.65rem;
        }
        .diagnostico-table .row-valid td { border-left: 3px solid #4caf50; }
        .diagnostico-table .row-duplicado td { border-left: 3px solid #ffc107; background: #fffde7; }
        .diagnostico-table .row-invalid td { border-left: 3px solid #f44336; }
        .diagnostico-scroll {
            max-height: 350px;
            overflow-y: auto;
            border-radius: 6px;
            border: 1px solid #eee;
            background: #fff;
        }
        .diagnostico-scroll::-webkit-scrollbar { width: 6px; }
        .diagnostico-scroll::-webkit-scrollbar-track { background: #f5f5f5; border-radius: 4px; }
        .diagnostico-scroll::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
        .table-count { color: #999; font-size: 0.55rem; }
        .seleccion-todos {
            color: #999;
            font-size: 0.6rem;
            cursor: pointer;
        }
        .seleccion-todos:hover { color: #666; }
        .error-list {
            background: #fff;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 6px;
            max-height: 150px;
            overflow-y: auto;
        }
        .error-list .error-item {
            font-size: 0.65rem;
            padding: 3px 0;
            color: #666;
            border-bottom: 1px solid #f5f5f5;
        }
        .equipo-checkbox {
            accent-color: #2e7d32;
            width: 14px;
            height: 14px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .grupo-header {
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .grupo-header:hover {
            background: #f5f5f5;
        }
        .grupo-contenido {
            padding-left: 20px;
            margin-top: 4px;
            border-left: 2px solid #eee;
            display: none;
        }
        .grupo-contenido.abierto {
            display: block;
        }
        .equipo-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 2px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .equipo-item .equipo-info {
            font-size: 0.65rem;
            color: #555;
        }
        .equipo-item .equipo-info .inv {
            color: #1a237e;
            font-weight: 600;
        }
        .equipo-item .equipo-info .detalle {
            color: #aaa;
            font-size: 0.55rem;
        }
        .equipo-item .equipo-info .error-text {
            color: #ccc;
            font-size: 0.5rem;
            margin-left: 4px;
        }
        .equipo-item .estado-icon {
            font-size: 0.55rem;
            width: 18px;
            text-align: center;
        }
        .equipo-item .estado-icon.valido { color: #4caf50; }
        .equipo-item .estado-icon.duplicado { color: #ffc107; }
        .equipo-item .estado-icon.invalido { color: #f44336; }
        .campo-editable {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            color: #333;
            font-size: 0.6rem;
            padding: 2px 6px;
            width: 100%;
            transition: all 0.3s ease;
        }
        .campo-editable:hover {
            border-color: #bbb;
            background: #fff;
        }
        .campo-editable:focus {
            border-color: #1a237e;
            outline: none;
            background: #fff;
            box-shadow: 0 0 0 2px rgba(26, 35, 126, 0.05);
        }
        .campo-editable.cambiado {
            border-color: #ffc107;
            background: #fffde7;
        }
        .carga-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 8px 12px;
        }
        .carga-grid label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #999;
            display: block;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .carga-grid input, .carga-grid select {
            width: 100%;
            padding: 4px 8px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #333;
            transition: border-color 0.2s;
        }
        .carga-grid input:focus, .carga-grid select:focus {
            border-color: #1a237e;
            outline: none;
            box-shadow: 0 0 0 2px rgba(26, 35, 126, 0.05);
        }
        .carga-grid select option {
            background: #fff;
            color: #333;
        }
        .campos-especificos {
            display: none;
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
        }
        .campos-especificos.visible {
            display: block;
        }
        .badge-tipo-mini {
            font-size: 0.55rem;
            padding: 1px 10px;
            border-radius: 10px;
            display: inline-block;
        }
        .badge-tipo-mini.pc { background: #e3f2fd; color: #0d47a1; }
        .badge-tipo-mini.ups { background: #fff8e1; color: #f57f17; }
        .badge-seleccionado {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.5rem;
            font-weight: 600;
        }
        .grupo-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 6px 10px;
            margin-bottom: 4px;
        }
        .grupo-card .grupo-ubicacion {
            color: #1a237e;
            font-weight: 600;
            font-size: 0.7rem;
        }
        .grupo-card .grupo-count {
            color: #999;
            font-size: 0.6rem;
        }
        .grupo-card .grupo-estado {
            font-size: 0.5rem;
            color: #ccc;
        }
        .grupo-card .grupo-check {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }
        .grupo-card .grupo-check label {
            color: #999;
            font-size: 0.55rem;
            cursor: pointer;
        }
        .grupo-card .grupo-check label:hover { color: #666; }

        .logs-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #eee;
            margin-top: 12px;
        }
        .logs-container .log-item {
            background: #fff;
            border: 1px solid #e8eaf6;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }
        .logs-container .log-item .log-info {
            font-size: 0.7rem;
            color: #555;
        }
        .logs-container .log-item .log-info .fecha {
            color: #999;
            font-size: 0.6rem;
        }
        .logs-container .log-item .log-info .usuario {
            color: #1a237e;
            font-weight: 600;
        }
        .logs-container .log-item .log-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .logs-container .log-item .log-actions .btn-sm {
            font-size: 0.6rem;
            padding: 2px 10px;
            border-radius: 12px;
        }

        .resumen-seleccion {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 6px;
            padding: 6px 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }
        .resumen-seleccion .contador {
            color: #2e7d32;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .resumen-seleccion .contador span {
            font-size: 1rem;
        }
        .resumen-seleccion .texto {
            color: #555;
            font-size: 0.65rem;
        }

        .footer-custom {
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 16px;
            text-align: center;
        }
        .footer-custom span {
            color: #ccc;
            font-size: 0.5rem;
            letter-spacing: 2px;
        }
        .separator {
            border-top: 1px solid #eee;
            margin: 12px 0;
        }
        .mt-1 { margin-top: 4px !important; }
        .mt-2 { margin-top: 8px !important; }
        .mt-3 { margin-top: 12px !important; }
        .mb-1 { margin-bottom: 4px !important; }
        .mb-2 { margin-bottom: 8px !important; }
        .mb-3 { margin-bottom: 12px !important; }
        .text-center { text-align: center; }
        .d-flex { display: flex; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .flex-wrap { flex-wrap: wrap; }
        .align-items-center { align-items: center; }
        .justify-content-between { justify-content: space-between; }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">
                <i class="bi bi-boxes"></i> SIR
            </a>
            <div>
                <?php if ($mostrar_logs): ?>
                    <a class="btn btn-outline-light" href="?ocultar_logs=1" style="margin-right:4px;">
                        <i class="bi bi-x-circle"></i> Ocultar logs
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-light" href="?ver_logs=1" style="margin-right:4px;">
                        <i class="bi bi-clock-history"></i> Ver logs
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline-light" href="buscar.php">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">

                <div class="text-center mb-3">
                    <h2 style="color: #1a237e; font-weight: 700; font-size: 1.5rem;">
                        <i class="bi bi-lightning-charge"></i> Carga Rápida
                    </h2>
                    <p style="color: #999; font-size: 0.75rem; letter-spacing: 1px; text-transform: uppercase;">
                        Sube tu Excel, edita, selecciona y importa
                    </p>
                </div>

                <?php if ($resultado): ?>
                    <div class="carga-result <?= strpos($resultado, '✅') !== false ? 'success' : (strpos($resultado, '⚠️') !== false ? 'info' : 'error') ?>">
                        <?= $resultado ?>
                    </div>
                <?php endif; ?>

                <!-- ============================================ -->
                <!-- LOGS DE IMPORTACIÓN                         -->
                <!-- ============================================ -->
                <?php if ($mostrar_logs): ?>
                <div class="logs-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:8px;">
                        <span style="color: #1a237e; font-weight: 600; font-size: 0.75rem;">
                            <i class="bi bi-clock-history"></i> HISTORIAL DE IMPORTACIONES
                        </span>
                        <span style="color:#aaa; font-size:0.55rem;">Últimas 50 importaciones</span>
                    </div>
                    
                    <?php if (empty($logs_importacion)): ?>
                        <div style="background:#fff; border-radius:6px; padding:12px; text-align:center; color:#aaa; font-size:0.7rem;">
                            No hay importaciones registradas.
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs_importacion as $log): 
                            $deshecho = $log['deshecho'] ?? 0;
                            $fecha_deshecho = $log['fecha_deshecho'] ?? null;
                            $equipos_importados = $log['equipos_importados'] ?? 0;
                        ?>
                        <div class="log-item" style="<?= $deshecho ? 'opacity:0.6;' : '' ?>">
                            <div class="log-info">
                                <span class="usuario"><i class="bi bi-person"></i> <?= htmlspecialchars($log['usuario']) ?></span>
                                <span class="fecha"><i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i', strtotime($log['fecha_importacion'])) ?></span>
                                <span style="color:#666; font-size:0.6rem;">
                                    <?= htmlspecialchars($log['nombre_archivo'] ?? 'manual') ?>
                                </span>
                                <span style="color:#2e7d32; font-size:0.65rem; font-weight:600;">
                                    <?= $equipos_importados ?> equipos
                                </span>
                                <?php if ($log['errores'] > 0): ?>
                                    <span style="color:#c62828; font-size:0.6rem;">⚠️ <?= $log['errores'] ?> errores</span>
                                <?php endif; ?>
                                <?php if ($deshecho): ?>
                                    <span class="btn-deshecho eliminado">
                                        <i class="bi bi-arrow-counterclockwise"></i> Deshecho
                                        <?php if ($fecha_deshecho): ?>
                                            <?= date('d/m/Y H:i', strtotime($fecha_deshecho)) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="btn-deshecho">✅ Activo</span>
                                <?php endif; ?>
                            </div>
                            <div class="log-actions">
                                <?php if (!$deshecho && $equipos_importados > 0): ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?deshacer=<?= $log['id'] ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('¿Estás seguro de deshacer la importación #<?= $log['id'] ?>? Se eliminarán <?= $equipos_importados ?> equipos.')">
                                        <i class="bi bi-arrow-counterclockwise"></i> Deshacer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="card card-custom">
                    <div class="card-header">
                        <i class="bi bi-upload"></i> CARGA MASIVA
                        <?php if ($diagnostico): ?>
                        <span style="color:rgba(255,255,255,0.4); font-size:0.7rem; margin-left:10px;">
                            <?= count($diagnostico['validos']) ?> válidos · 
                            <?= count($diagnostico['invalidos']) ?> inválidos
                            <?php if ($total_duplicados > 0): ?>
                            · <span style="color:#ffc107;"><?= $total_duplicados ?> duplicados</span>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">

                        <?php if (!$diagnostico || empty($diagnostico['todos_los_datos'])): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 12px; border: 1px solid #eee;">
                                <div class="carga-tip">
                                    <i class="bi bi-info-circle"></i> Formatos soportados:
                                    <code>.xlsx</code> <code>.xls</code> <code>.csv</code>
                                    <br>Columnas esperadas:
                                    <code>CC, Nombre CC, Inventario, Activo, Nombre Equipo, Estatus, Fecha, Marca, Modelo, Serie</code>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-8">
                                        <input type="file" class="form-control form-control-custom" name="archivo_excel" accept=".xlsx,.xls,.csv" required style="padding:8px 12px; font-size:0.85rem;">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" name="procesar_excel" class="carga-btn" style="padding:8px 16px; width:100%; font-size:0.75rem;">
                                            <i class="bi bi-search"></i> PROCESAR
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php if ($diagnostico && !empty($diagnostico['todos_los_datos'])): ?>
                        
                        <div class="section-divider"></div>

                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:10px;">
                            <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                <?php if (!empty($diagnostico['invalidos'])): ?>
                                <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?exportar_errores=1" class="btn-exportar-errores">
                                    <i class="bi bi-download"></i> Exportar errores
                                    <span style="background:rgba(255,215,0,0.1); padding:0 4px; border-radius:3px; font-size:0.6rem;">
                                        <?= count($diagnostico['invalidos']) ?>
                                    </span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?limpiar=1" class="btn-limpiar" onclick="return confirm('¿Limpiar diagnóstico? Los datos se perderán.');">
                                <i class="bi bi-eraser"></i> Limpiar todo
                            </a>
                        </div>

                        <?php if (!empty($diagnostico['grupos'])): ?>
                        <div style="background: #f8f9fa; border-radius: 8px; padding: 12px; border: 1px solid #eee; margin-bottom: 12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:8px;">
                                <div>
                                    <span style="color: #666; font-size: 0.6rem; letter-spacing: 1px; text-transform: uppercase;">
                                        <i class="bi bi-check2-square" style="color: #2e7d32;"></i> SELECCIONAR EQUIPOS PARA IMPORTAR
                                    </span>
                                    <span style="color:#aaa; font-size:0.55rem; margin-left:6px;">
                                        (<?= $total_grupos ?> grupos · <?= $total_validos ?> válidos)
                                        <?php if ($total_duplicados > 0): ?>
                                        · <span style="color:#f57f17;"><?= $total_duplicados ?> duplicados</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <span class="seleccion-todos" onclick="seleccionarTodosEquipos(true)" style="color:#2e7d32;">
                                        ✅ Seleccionar todos
                                    </span>
                                    <span class="seleccion-todos" onclick="seleccionarTodosEquipos(false)">
                                        ❌ Deseleccionar
                                    </span>
                                </div>
                            </div>
                            
                            <form method="POST" id="formSeleccionarEquipos">
                                <div style="max-height:400px; overflow-y:auto; border-radius:6px; background:#fff; padding:4px; border:1px solid #eee;">
                                    
                                    <?php foreach ($diagnostico['grupos'] as $key => $grupo): 
                                        $tipo_clase = strtolower($grupo['tipo']);
                                        $tiene_validos = ($grupo['validos'] ?? 0) > 0;
                                        $total_validos_grupo = $grupo['validos'] ?? 0;
                                        $total_duplicados_grupo = $grupo['duplicados'] ?? 0;
                                        $grupo_id = md5($key);
                                    ?>
                                    <div style="margin-bottom:6px; border:1px solid #f0f0f0; border-radius:6px; padding:4px 8px; background:#fafafa;">
                                        
                                        <div class="grupo-header d-flex justify-content-between align-items-center" onclick="toggleGrupo('<?= $grupo_id ?>')">
                                            <div class="d-flex align-items-center gap-1">
                                                <span id="icon_<?= $grupo_id ?>" style="color:#aaa; font-size:0.5rem;">▶</span>
                                                <span style="color: #1a237e; font-weight: 600; font-size: 0.7rem;">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($grupo['ubicacion']) ?>
                                                </span>
                                                <span class="badge-tipo <?= $tipo_clase ?>"><?= $grupo['tipo'] ?></span>
                                                <?php if ($tiene_validos): ?>
                                                    <span style="color:#2e7d32; font-size:0.5rem;">(<?= $total_validos_grupo ?> válidos)</span>
                                                <?php endif; ?>
                                                <?php if ($total_duplicados_grupo > 0): ?>
                                                    <span style="color:#f57f17; font-size:0.5rem;">⚠️ <?= $total_duplicados_grupo ?> duplicados</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span style="color:#aaa; font-size:0.45rem;"><?= $grupo['total'] ?> equipos</span>
                                                <?php if ($tiene_validos): ?>
                                                    <input type="checkbox" class="grupo-selector" data-grupo="<?= $grupo_id ?>" 
                                                           onchange="seleccionarGrupo('<?= $grupo_id ?>', this.checked)" checked>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
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
                                                           class="equipo-checkbox grupo-<?= $grupo_id ?>" checked
                                                           onchange="actualizarPrevisualizacion()">
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
                                
                                <div class="d-flex gap-1 flex-wrap mt-2">
                                    <button type="submit" name="seleccionar_equipos" class="carga-btn-success" id="btnSeleccionarEquipos">
                                        <i class="bi bi-check-lg"></i> IMPORTAR EQUIPOS SELECCIONADOS
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <?php 
                        $hay_seleccionados = false;
                        if ($diagnostico && isset($diagnostico['validos']) && count($diagnostico['validos']) > 0) {
                            $hay_seleccionados = true;
                        }
                        ?>
                        
                        <?php if ($hay_seleccionados || !empty($diagnostico['validos'])): ?>
                        <form method="POST" id="formImportar">
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 12px; border: 1px solid #eee; margin-bottom: 12px;">
                                <div style="color: #999; font-size: 0.6rem; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px;">
                                    <i class="bi bi-gear" style="color: #f57f17;"></i> DATOS CONSTANTES
                                    <span style="color:#ccc; font-size:0.5rem; font-weight:normal; text-transform:none;">
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
                                    <div style="color: #999; font-size: 0.55rem; letter-spacing: 0.5px; margin-bottom: 4px;">
                                        <span class="badge-tipo-mini pc">💻 PC</span>
                                        <span style="color:#ccc; font-size:0.5rem;">(solo para equipos PC)</span>
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
                                    <div style="color: #999; font-size: 0.55rem; letter-spacing: 0.5px; margin-bottom: 4px;">
                                        <span class="badge-tipo-mini ups">⚡ UPS</span>
                                        <span style="color:#ccc; font-size:0.5rem;">(solo para equipos UPS)</span>
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

                            <div class="diagnostico-container">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                                    <div style="color: #2e7d32; font-weight: 700; font-size: 0.75rem; letter-spacing:0.5px;">
                                        <i class="bi bi-pencil" style="color:#f57f17;"></i> EDICIÓN EN LÍNEA
                                        <span style="color:#aaa; font-size:0.6rem; font-weight:normal;">
                                            (click en los campos para editar)
                                        </span>
                                    </div>
                                    <span style="color:#aaa; font-size:0.6rem;" id="contadorPrevisualizacion">
                                        <?= count($diagnostico['validos']) ?> equipos válidos
                                    </span>
                                </div>

                                <!-- ============================================ -->
                                <!-- PREVISUALIZACIÓN DINÁMICA                   -->
                                <!-- ============================================ -->
                                <?php if (!empty($diagnostico['validos'])): ?>
                                <div style="background:#fff; border-radius:6px; padding:4px 8px; border:1px solid #e8f5e9;">
                                    <div style="color:#aaa; font-size:0.5rem; letter-spacing:0.5px; margin-bottom:4px;">
                                        <i class="bi bi-table"></i> PREVISUALIZACIÓN
                                        <span class="table-count" id="contadorTabla">(<?= count($diagnostico['validos']) ?> equipos seleccionados)</span>
                                    </div>
                                    <div class="diagnostico-scroll" id="tablaScroll">
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
                                            <tbody id="tablaBody">
                                                <?php foreach ($diagnostico['validos'] as $index => $equipo): 
                                                    $tipo_clase = strtolower($equipo['tipo'] ?? 'otros');
                                                    $row_id = 'row_' . $index;
                                                ?>
                                                <tr class="row-valid" id="<?= $row_id ?>" data-inventario="<?= htmlspecialchars($equipo['inventario']) ?>">
                                                    <td><?= $equipo['fila'] ?></td>
                                                    <td><strong style="color:#1a237e;"><?= htmlspecialchars($equipo['inventario'] ?? '') ?></strong></td>
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
                                    <div style="color:#ccc; font-size:0.45rem; padding:4px 4px; text-align:right;">
                                        <i class="bi bi-info-circle"></i> Los cambios se guardan automáticamente al importar
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Resumen de selección -->
                                <div class="resumen-seleccion mt-2" id="resumenSeleccion">
                                    <span class="texto">
                                        <i class="bi bi-check-circle-fill" style="color:#2e7d32;"></i>
                                        Equipos seleccionados para importar:
                                    </span>
                                    <span class="contador">
                                        <span id="totalSeleccionados"><?= count($diagnostico['validos']) ?></span> equipos
                                    </span>
                                </div>

                                <?php if (!empty($diagnostico['invalidos'])): ?>
                                <div style="margin-top:10px;">
                                    <div style="color:#aaa; font-size:0.5rem; letter-spacing:0.5px; margin-bottom:4px;">
                                        <i class="bi bi-exclamation-triangle" style="color:#c62828;"></i> EQUIPOS OMITIDOS (NO SE IMPORTARÁN)
                                        <span style="color:#ccc; font-size:0.45rem;">(<?= count($diagnostico['invalidos']) ?> equipos)</span>
                                        <?php if (!empty($diagnostico['invalidos'])): ?>
                                        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?exportar_errores=1" class="btn-exportar-errores" style="font-size:0.5rem; padding:1px 10px; margin-left:6px;">
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
                                            <strong style="color:<?= $es_duplicado ? '#f57f17' : '#c62828' ?>;">
                                                Fila <?= $equipo['fila'] ?>
                                            </strong> 
                                            (Inv: <?= htmlspecialchars($equipo['inventario'] ?? 'N/A') ?>):
                                            <?php foreach ($equipo['errores'] as $error): ?>
                                                <span class="badge-<?= $badge_clase ?>" style="margin-left:2px; font-size:0.5rem;"><?= $error ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div style="background:#f8f9fa; border-radius:6px; padding:10px; border:1px solid #e8f5e9; margin-top:10px;">
                                <input type="hidden" name="confirmar_importacion" value="1">
                                <button type="submit" class="carga-btn-success" <?= empty($diagnostico['validos']) ? 'disabled' : '' ?> style="width:100%;" id="btnImportar">
                                    <i class="bi bi-check-lg"></i> 
                                    IMPORTAR <span id="btnContadorImportar"><?= count($diagnostico['validos']) ?></span> EQUIPOS VÁLIDOS
                                </button>
                                <div style="text-align:center; margin-top:4px; color:#ccc; font-size:0.5rem;">
                                    <?= count($diagnostico['invalidos']) ?> equipos con errores serán omitidos
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php endif; ?>

                        <div class="separator"></div>
                        <a href="buscar.php" class="btn-outline-custom">
                            <i class="bi bi-arrow-left"></i> Volver al buscador
                        </a>

                    </div>
                </div>

                <div class="footer-custom">
                    <span><i class="bi bi-cpu"></i> SIR v3.0</span>
                </div>

            </div>
        </div>
    </div>

    <script>
    // ============================================
    // FUNCIONES PARA SELECCIÓN POR EQUIPO
    // ============================================

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

    function seleccionarGrupo(grupoId, seleccionar) {
        var checkboxes = document.querySelectorAll('.grupo-' + grupoId);
        checkboxes.forEach(function(cb) {
            cb.checked = seleccionar;
        });
        actualizarContador();
        actualizarPrevisualizacion();
    }

    function seleccionarTodosEquipos(seleccionar) {
        var checkboxes = document.querySelectorAll('.equipo-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = seleccionar;
        });
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
        actualizarPrevisualizacion();
    }

    // ============================================
    // ACTUALIZAR PREVISUALIZACIÓN
    // ============================================
    function actualizarPrevisualizacion() {
        var seleccionados = [];
        document.querySelectorAll('.equipo-checkbox:checked').forEach(function(cb) {
            seleccionados.push(cb.value);
        });

        var filas = document.querySelectorAll('#tablaBody tr');
        var contadorVisibles = 0;
        filas.forEach(function(tr) {
            var inventario = tr.getAttribute('data-inventario');
            if (seleccionados.includes(inventario)) {
                tr.style.display = '';
                contadorVisibles++;
            } else {
                tr.style.display = 'none';
            }
        });

        document.getElementById('totalSeleccionados').textContent = contadorVisibles;
        document.getElementById('contadorTabla').textContent = '(' + contadorVisibles + ' equipos seleccionados)';
        document.getElementById('contadorPrevisualizacion').textContent = contadorVisibles + ' equipos válidos';
        document.getElementById('btnContadorImportar').textContent = contadorVisibles;

        var btnImportar = document.getElementById('btnImportar');
        if (contadorVisibles === 0) {
            btnImportar.disabled = true;
        } else {
            btnImportar.disabled = false;
        }

        var btnSeleccionar = document.getElementById('btnSeleccionarEquipos');
        if (btnSeleccionar) {
            btnSeleccionar.innerHTML = '<i class="bi bi-check-lg"></i> IMPORTAR ' + contadorVisibles + ' EQUIPOS SELECCIONADOS';
            btnSeleccionar.disabled = (contadorVisibles === 0);
        }
    }

    function actualizarContador() {
        var seleccionados = document.querySelectorAll('.equipo-checkbox:checked').length;
        var btn = document.getElementById('btnSeleccionarEquipos');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-check-lg"></i> IMPORTAR ' + seleccionados + ' EQUIPOS SELECCIONADOS';
            btn.disabled = (seleccionados === 0);
        }
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('equipo-checkbox')) {
            actualizarContador();
            actualizarPrevisualizacion();
            
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

    var cambiosPendientes = {};

    function actualizarEquipo(rowId, campo, valor) {
        if (!cambiosPendientes[rowId]) {
            cambiosPendientes[rowId] = {};
        }
        cambiosPendientes[rowId][campo] = valor;
        
        var input = document.querySelector('#tablaEditable input[data-row="' + rowId + '"][data-field="' + campo + '"]');
        if (input) {
            input.classList.add('cambiado');
            setTimeout(function() {
                input.classList.remove('cambiado');
            }, 1500);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        actualizarContador();
        actualizarPrevisualizacion();
        
        document.querySelectorAll('.grupo-contenido').forEach(function(el) {
            el.classList.add('abierto');
        });
        document.querySelectorAll('.grupo-header .icono').forEach(function(el) {
            el.textContent = '▼';
        });
    });
    </script>

</body>
</html>
