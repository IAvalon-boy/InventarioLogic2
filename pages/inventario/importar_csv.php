<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$message_type = '';
$diagnostico = null;
$preview_data = [];
$todos_los_datos = [];

// ============================================
// LIMPIAR SESIÓN
// ============================================
if (isset($_GET['limpiar'])) {
    unset($_SESSION['import_temp']);
    unset($_SESSION['import_datos']);
    header('Location: importar_csv.php');
    exit;
}

// ============================================
// PASO 1: SUBIR Y ANALIZAR CSV
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '❌ Error al subir el archivo. Código: ' . $file['error'];
        $message_type = 'danger';
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $message = '❌ Solo se permiten archivos CSV.';
            $message_type = 'danger';
        } else {
            // Leer CSV
            $rows = [];
            if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
            
            if (empty($rows)) {
                $message = '❌ El archivo CSV está vacío.';
                $message_type = 'danger';
            } else {
                $diagnostico = analizarArchivoCSV($rows);
                
                if ($diagnostico['error']) {
                    $message = '❌ ' . $diagnostico['mensaje'];
                    $message_type = 'danger';
                } else {
                    // Guardar TODO en sesión
                    $_SESSION['import_temp'] = $diagnostico;
                    $_SESSION['import_datos'] = [
                        'rows' => $rows,
                        'fila_inicio' => $diagnostico['fila_inicio']
                    ];
                    $message = '✅ Archivo analizado correctamente. Revisa el diagnóstico.';
                    $message_type = 'success';
                }
            }
        }
    }
}

// ============================================
// PASO 2: CONFIRMAR Y GUARDAR
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_importacion'])) {
    $diagnostico = $_SESSION['import_temp'] ?? null;
    $datos_guardados = $_SESSION['import_datos'] ?? null;
    
    if (!$diagnostico || !$datos_guardados) {
        $message = '❌ No hay datos para importar.';
        $message_type = 'danger';
    } else {
        $resultado = ejecutarImportacionCSV($db, $diagnostico, $datos_guardados, $_POST);
        $message = $resultado['mensaje'];
        $message_type = $resultado['tipo'];
        $preview_data = $resultado['errores'] ?? [];
        
        // Limpiar sesión después de importar
        unset($_SESSION['import_temp']);
        unset($_SESSION['import_datos']);
    }
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

function analizarArchivoCSV($rows) {
    $resultado = [
        'error' => false,
        'mensaje' => '',
        'tipo' => '',
        'total_filas' => 0,
        'columnas' => [],
        'datos_muestra' => [],
        'todos_los_datos' => [],
        'columnas_encontradas' => [],
        'columnas_faltantes' => [],
        'columnas_extra' => [],
        'filas_validas' => 0,
        'filas_invalidas' => 0,
        'errores_filas' => [],
        'campos_requeridos' => [],
        'sugerencias' => [],
        'fila_inicio' => 0
    ];
    
    // Mapas de sinónimos
    $mapa_inventario = [
        'inventario' => ['inventario', 'num inventario', 'n° inventario', 'codigo', 'código'],
        'activo' => ['activo', 'num activo', 'n° activo'],
        'cc' => ['cc', 'centro costo', 'centro de costo'],
        'responsable' => ['responsable', 'encargado', 'usuario', 'solicitante', 'nombre'],
        'ubicacion' => ['ubicacion', 'ubicación', 'area', 'departamento', 'servicio'],
        'telefono' => ['telefono', 'teléfono', 'tel'],
        'tipo' => ['tipo', 'equipo', 'tipo equipo'],
        'marca' => ['marca'],
        'modelo' => ['modelo'],
        'serie' => ['serie', 'serial'],
        'f_compra' => ['f_compra', 'fecha compra'],
        'venc_garantia' => ['venc_garantia', 'vencimiento garantia'],
        'estado' => ['estado', 'estatus'],
        'nivel' => ['nivel', 'nivel equipo'],
        'procesador' => ['procesador'],
        'ram' => ['ram', 'memoria'],
        'hdd' => ['hdd', 'disco duro'],
        'so' => ['so', 'sistema operativo'],
        'user_dom' => ['user_dom', 'usuario dominio']
    ];
    
    $mapa_requerimiento = [
        'requerimiento' => ['requerimiento', 'num requerimiento', 'n° requerimiento'],
        'inventario' => ['inventario', 'num inventario', 'n° inventario'],
        'cc' => ['cc', 'centro costo'],
        'responsable' => ['responsable', 'encargado', 'usuario', 'solicitante'],
        'ubicacion' => ['ubicacion', 'ubicación', 'area', 'departamento'],
        'telefono' => ['telefono', 'teléfono', 'tel'],
        'tipo' => ['tipo', 'equipo'],
        'falla' => ['falla', 'descripcion', 'descripción', 'problema'],
        'servicio' => ['servicio', 'tipo servicio'],
        'atencion' => ['atencion', 'tipo atencion'],
        'estatus' => ['estatus', 'estado', 'status'],
        'insertdate' => ['insertdate', 'fecha', 'fecha solicitud']
    ];
    
    // Buscar encabezados
    $header = null;
    $fila_inicio = 0;
    $tipo_detectado = '';
    $mapa_actual = [];
    
    for ($i = 0; $i < min(20, count($rows)); $i++) {
        $row = $rows[$i];
        $row_str = implode(' ', array_filter($row));
        $row_str_lower = strtolower($row_str);
        
        $es_inventario = strpos($row_str_lower, 'inventario') !== false || 
                         strpos($row_str_lower, 'marca') !== false;
        
        $es_requerimiento = strpos($row_str_lower, 'requerimiento') !== false || 
                            strpos($row_str_lower, 'falla') !== false;
        
        if ($es_inventario && !$es_requerimiento) {
            $tipo_detectado = 'inventario';
            $mapa_actual = $mapa_inventario;
            $header = $row;
            $fila_inicio = $i + 1;
            break;
        } elseif ($es_requerimiento) {
            $tipo_detectado = 'requerimiento';
            $mapa_actual = $mapa_requerimiento;
            $header = $row;
            $fila_inicio = $i + 1;
            break;
        }
    }
    
    if (!$header) {
        $resultado['error'] = true;
        $resultado['mensaje'] = 'No se detectó el formato. Asegúrate de tener encabezados como "inventario", "responsable", etc.';
        return $resultado;
    }
    
    $resultado['tipo'] = $tipo_detectado;
    $resultado['total_filas'] = count($rows) - $fila_inicio;
    $resultado['fila_inicio'] = $fila_inicio;
    
    // Mapear columnas
    $header_limpio = array_map(function($col) {
        $col = trim(strtolower($col));
        $col = preg_replace('/[^a-z0-9 ]/', '', $col);
        return $col;
    }, $header);
    
    $columnas_encontradas = [];
    $columnas_extra = [];
    
    foreach ($header_limpio as $index => $col) {
        if (empty($col)) continue;
        
        $encontrado = false;
        foreach ($mapa_actual as $campo => $sinonimos) {
            if (in_array($col, $sinonimos)) {
                $columnas_encontradas[$campo] = $index;
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            $columnas_extra[] = $col;
        }
    }
    
    $campos_requeridos = ($tipo_detectado == 'inventario') 
        ? ['inventario', 'responsable', 'tipo']
        : ['inventario', 'responsable', 'falla'];
    
    $campos_faltantes = [];
    foreach ($campos_requeridos as $campo) {
        if (!isset($columnas_encontradas[$campo])) {
            $campos_faltantes[] = $campo;
        }
    }
    
    $resultado['columnas'] = $columnas_encontradas;
    $resultado['columnas_faltantes'] = $campos_faltantes;
    $resultado['columnas_extra'] = $columnas_extra;
    $resultado['campos_requeridos'] = $campos_requeridos;
    
    // Extraer y validar datos - GUARDAR TODOS LOS DATOS
    $datos_muestra = [];
    $todos_los_datos = [];
    $errores_filas = [];
    $filas_validas = 0;
    $filas_invalidas = 0;
    $max_muestra = 10;
    $num_fila = 0;
    
    for ($i = $fila_inicio; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;
        
        $num_fila++;
        $fila_data = [];
        foreach ($columnas_encontradas as $campo => $col_index) {
            $valor = isset($row[$col_index]) ? trim($row[$col_index]) : '';
            $fila_data[$campo] = $valor;
        }
        
        // Guardar todos los datos
        $todos_los_datos[] = $fila_data;
        
        // Validar
        $fila_valida = true;
        $errores_fila = [];
        foreach ($campos_requeridos as $campo) {
            if (isset($fila_data[$campo]) && empty($fila_data[$campo])) {
                $fila_valida = false;
                $errores_fila[] = ucfirst($campo) . ' vacío';
            }
        }
        
        if ($fila_valida) {
            $filas_validas++;
            if (count($datos_muestra) < $max_muestra) {
                $datos_muestra[] = $fila_data;
            }
        } else {
            $filas_invalidas++;
            $errores_filas[] = 'Fila ' . $num_fila . ': ' . implode(', ', $errores_fila);
        }
    }
    
    $resultado['datos_muestra'] = $datos_muestra;
    $resultado['todos_los_datos'] = $todos_los_datos;
    $resultado['filas_validas'] = $filas_validas;
    $resultado['filas_invalidas'] = $filas_invalidas;
    $resultado['errores_filas'] = array_slice($errores_filas, 0, 10);
    
    $sugerencias = [];
    if (!empty($campos_faltantes)) {
        $sugerencias[] = '⚠️ Campos faltantes: ' . implode(', ', $campos_faltantes);
    }
    if ($filas_invalidas > 0) {
        $sugerencias[] = '⚠️ ' . $filas_invalidas . ' filas incompletas';
    }
    if (!empty($columnas_extra)) {
        $sugerencias[] = 'ℹ️ Columnas ignoradas: ' . implode(', ', $columnas_extra);
    }
    if ($filas_validas > 0) {
        $sugerencias[] = '✅ ' . $filas_validas . ' filas listas para importar';
    }
    
    $resultado['sugerencias'] = $sugerencias;
    $resultado['mensaje'] = 'Archivo ' . ucfirst($tipo_detectado) . ' analizado correctamente.';
    
    return $resultado;
}

function ejecutarImportacionCSV($db, $diagnostico, $datos_guardados, $post) {
    $tipo = $diagnostico['tipo'];
    $columnas = $diagnostico['columnas'];
    $todos_los_datos = $diagnostico['todos_los_datos'];
    
    $cc_default = $post['cc_default'] ?? '';
    $nivel_default = $post['nivel_default'] ?? 'NIVEL 1-A';
    $estado_default = $post['estado_default'] ?? '1';
    $user = Session::get('user_id');
    $hoy = date('Y-m-d');
    
    $registrados = 0;
    $errores = [];
    
    // Determinar tabla según tipo
    foreach ($todos_los_datos as $fila) {
        $tipo_equipo = strtoupper($fila['tipo'] ?? 'PC');
        $tabla = match($tipo_equipo) {
            'PC' => 't_inventpc',
            'IMPRESORA' => 't_impresores',
            'UPS' => 't_ups',
            'OTROS' => 't_otros',
            default => 't_inventpc'
        };
        
        $data = [
            'inventario' => $fila['inventario'] ?? '',
            'activo' => $fila['activo'] ?? '',
            'cc' => $fila['cc'] ?? $cc_default,
            'responsable' => strtoupper($fila['responsable'] ?? ''),
            'ubicacion' => strtoupper($fila['ubicacion'] ?? ''),
            'telefono' => $fila['telefono'] ?? '',
            'tipo' => $tipo_equipo,
            'marca' => $fila['marca'] ?? '',
            'modelo' => strtoupper($fila['modelo'] ?? ''),
            'serie' => strtoupper($fila['serie'] ?? ''),
            'f_compra' => $fila['f_compra'] ?? $hoy,
            'venc_garantia' => $fila['venc_garantia'] ?? null,
            'estadoEquipo' => $estado_default,
            'Nivel' => $nivel_default,
            'procesador' => strtoupper($fila['procesador'] ?? ''),
            'ram' => $fila['ram'] ?? '',
            'hdd' => $fila['hdd'] ?? '',
            'so' => $fila['so'] ?? '',
            'user_dom' => strtoupper($fila['user_dom'] ?? ''),
            'insertuser' => $user,
            'insertdate' => $hoy
        ];
        
        // Verificar si ya existe
        $existe = $db->fetchOne("SELECT inventario FROM $tabla WHERE inventario = ?", [$data['inventario']]);
        if ($existe) {
            $errores[] = "Inventario " . $data['inventario'] . " ya existe";
            continue;
        }
        
        try {
            $db->insert($tabla, $data);
            $registrados++;
        } catch (Exception $e) {
            $errores[] = "Error en " . $data['inventario'] . ": " . $e->getMessage();
        }
    }
    
    $mensaje = "✅ Se importaron <strong>$registrados</strong> registros de tipo <strong>" . ucfirst($tipo) . "</strong>.";
    if (!empty($errores)) {
        $mensaje .= " ⚠️ " . count($errores) . " errores.";
    }
    
    return [
        'mensaje' => $mensaje,
        'tipo' => $registrados > 0 ? 'success' : 'danger',
        'errores' => $errores
    ];
}

// ============================================
// CARGAR DIAGNÓSTICO DESDE SESIÓN
// ============================================
if (isset($_SESSION['import_temp']) && !empty($_SESSION['import_temp'])) {
    $diagnostico = $_SESSION['import_temp'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar CSV - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php"><i class="bi bi-boxes"></i> SIbD</a>
            <a class="btn btn-outline-light" href="buscar.php"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-file-earmark-excel"></i> Importar desde CSV
                        <span class="badge bg-light text-dark ms-2">Análisis inteligente</span>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                                <?= $message ?>
                                <?php if (!empty($preview_data) && is_array($preview_data)): ?>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($preview_data as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- PASO 1: SUBIR ARCHIVO                      -->
                        <!-- ========================================== -->
                        <?php if (!isset($_SESSION['import_temp']) || empty($_SESSION['import_temp'])): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">
                                        <i class="bi bi-file-earmark-excel"></i> Archivo CSV
                                    </label>
                                    <input type="file" class="form-control" name="archivo_csv" 
                                           accept=".csv" required>
                                    <small class="text-muted">Formato: CSV (separado por comas)</small>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Analizar
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="alert alert-info small">
                                    <i class="bi bi-lightbulb"></i>
                                    <strong>¿Cómo funciona?</strong>
                                    <ol class="mb-0">
                                        <li>Sube un archivo <strong>CSV</strong></li>
                                        <li>El sistema lo <strong>analiza</strong> automáticamente</li>
                                        <li>Revisa el <strong>diagnóstico</strong></li>
                                        <li><strong>Confirma</strong> la importación</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-secondary small">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Columnas que busca:</strong>
                                    <ul class="mb-0">
                                        <li><strong>Inventario:</strong> inventario, responsable, tipo</li>
                                        <li><strong>Requerimientos:</strong> inventario, responsable, falla</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- PASO 2: DIAGNÓSTICO                        -->
                        <!-- ========================================== -->
                        <?php if (isset($diagnostico) && !empty($diagnostico)): ?>
                        <div class="alert alert-<?= $diagnostico['filas_validas'] > 0 ? 'success' : 'danger' ?>">
                            <i class="bi bi-<?= $diagnostico['filas_validas'] > 0 ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                            <strong>Diagnóstico del archivo</strong><br>
                            <span class="badge bg-info"><?= ucfirst($diagnostico['tipo']) ?></span>
                            <span class="badge bg-secondary"><?= $diagnostico['total_filas'] ?> filas totales</span>
                            <span class="badge bg-success"><?= $diagnostico['filas_validas'] ?> válidas</span>
                            <?php if ($diagnostico['filas_invalidas'] > 0): ?>
                                <span class="badge bg-danger"><?= $diagnostico['filas_invalidas'] ?> inválidas</span>
                            <?php endif; ?>
                        </div>

                        <!-- Columnas detectadas -->
                        <div class="card mb-2">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-table"></i> Columnas detectadas
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>✅ Encontradas:</strong>
                                        <ul class="mb-0">
                                            <?php foreach ($diagnostico['columnas'] as $campo => $index): ?>
                                                <li><code><?= $campo ?></code> (columna <?= $index + 1 ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($diagnostico['columnas_faltantes'])): ?>
                                            <strong>❌ Faltantes (se pedirán manualmente):</strong>
                                            <ul class="mb-0 text-danger">
                                                <?php foreach ($diagnostico['columnas_faltantes'] as $campo): ?>
                                                    <li><code><?= $campo ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($diagnostico['columnas_extra'])): ?>
                                            <strong>ℹ️ Extra (ignoradas):</strong>
                                            <ul class="mb-0 text-muted">
                                                <?php foreach ($diagnostico['columnas_extra'] as $col): ?>
                                                    <li><code><?= $col ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sugerencias -->
                        <?php if (!empty($diagnostico['sugerencias'])): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-lightbulb"></i>
                            <strong>Sugerencias:</strong>
                            <ul class="mb-0">
                                <?php foreach ($diagnostico['sugerencias'] as $sug): ?>
                                    <li><?= $sug ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <!-- Errores de filas -->
                        <?php if (!empty($diagnostico['errores_filas'])): ?>
                        <div class="card mb-2 border-danger">
                            <div class="card-header bg-danger text-white">
                                <i class="bi bi-exclamation-triangle"></i> Errores en filas
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <?php foreach ($diagnostico['errores_filas'] as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Previsualización de datos -->
                        <?php if (!empty($diagnostico['datos_muestra'])): ?>
                        <div class="card mb-2">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-eye"></i> Previsualización (primeros <?= count($diagnostico['datos_muestra']) ?> registros)
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <?php foreach (array_keys($diagnostico['datos_muestra'][0]) as $campo): ?>
                                                    <th><?= ucfirst(str_replace('_', ' ', $campo)) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($diagnostico['datos_muestra'] as $fila): ?>
                                                <tr>
                                                    <?php foreach ($fila as $valor): ?>
                                                        <td><?= htmlspecialchars($valor ?? '') ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">
                                    Total de filas válidas: <strong><?= $diagnostico['filas_validas'] ?></strong>
                                    <?php if ($diagnostico['filas_validas'] > count($diagnostico['datos_muestra'])): ?>
                                        (mostrando <?= count($diagnostico['datos_muestra']) ?> de <?= $diagnostico['filas_validas'] ?>)
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- PASO 3: CONFIRMAR IMPORTACIÓN             -->
                        <!-- ========================================== -->
                        <?php if ($diagnostico['filas_validas'] > 0): ?>
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-check-circle"></i> Confirmar importación
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-2">
                                        <?php if (in_array('cc', $diagnostico['columnas_faltantes']) || $diagnostico['tipo'] == 'inventario'): ?>
                                        <div class="col-md-3">
                                            <label class="form-label small">CC (Centro de Costo)</label>
                                            <input type="text" class="form-control form-control-sm" name="cc_default" 
                                                   placeholder="Ej: 526107">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array('nivel', $diagnostico['columnas_faltantes']) || $diagnostico['tipo'] == 'inventario'): ?>
                                        <div class="col-md-3">
                                            <label class="form-label small">Nivel</label>
                                            <input type="text" class="form-control form-control-sm" name="nivel_default" 
                                                   value="NIVEL 1-A">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label small">Estado</label>
                                            <select class="form-select form-select-sm" name="estado_default">
                                                <option value="1">Activo</option>
                                                <option value="0">Inactivo</option>
                                                <option value="2">Mantenimiento</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="submit" name="confirmar_importacion" class="btn btn-success w-100">
                                                <i class="bi bi-check-lg"></i> Importar <?= $diagnostico['filas_validas'] ?> registros
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row g-2 mt-2">
                            <div class="col-md-6">
                                <a href="importar_csv.php?limpiar=1" class="btn btn-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Cancelar
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="buscar.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-left"></i> Volver
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>