<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// ============================================
// OBTENER FILTROS
// ============================================
$tipo = $_GET['tipo'] ?? 'todos';
$filtro = $_GET['filtro'] ?? 'todos';
$criterio = $_GET['criterio'] ?? '';
$mensaje = '';

// ============================================
// CONSULTAR TODOS LOS EQUIPOS
// ============================================
$equipos = [];

// 1. PCs
$pc = $db->fetchAll("SELECT inventario, activo, cc, responsable, ubicacion, telefono, 
                            tipo, marca, modelo, serie, 
                            f_compra, venc_garantia, estadoEquipo, Nivel, 
                            'PC' as tipo_equipo FROM t_inventpc");
if (!empty($pc)) $equipos = array_merge($equipos, $pc);

// 2. Impresoras
$imp = $db->fetchAll("SELECT inventario, activo, cc, responsable, ubicacion, telefono, 
                            tipo, marca, modelo, serie, 
                            f_compra, venc_garantia, estadoEquipo, Nivel, 
                            'IMPRESORA' as tipo_equipo FROM t_impresores");
if (!empty($imp)) $equipos = array_merge($equipos, $imp);

// 3. UPS
$ups = $db->fetchAll("SELECT inventario, activo, cc, responsable, ubicacion, telefono, 
                            tipo, marca, modelo, serie, 
                            f_compra, venc_garantia, estadoEquipo, Nivel, 
                            'UPS' as tipo_equipo FROM t_ups");
if (!empty($ups)) $equipos = array_merge($equipos, $ups);

// 4. Otros
$otros = $db->fetchAll("SELECT inventario, activo, cc, responsable, ubicacion, telefono, 
                              tipo, marca, modelo, serie, 
                              f_compra, venc_garantia, estadoEquipo, Nivel, 
                              'OTROS' as tipo_equipo FROM t_otros");
if (!empty($otros)) $equipos = array_merge($equipos, $otros);

// ============================================
// APLICAR FILTROS UNIFICADOS
// ============================================

// Filtro por tipo de equipo
if ($tipo != 'todos') {
    $mapa_tipo = ['pc' => 'PC', 'impresora' => 'IMPRESORA', 'ups' => 'UPS', 'otros' => 'OTROS'];
    $tipo_buscar = $mapa_tipo[$tipo] ?? '';
    if ($tipo_buscar) {
        $equipos = array_filter($equipos, function($e) use ($tipo_buscar) {
            return $e['tipo_equipo'] == $tipo_buscar;
        });
        $equipos = array_values($equipos);
    }
}

// Búsqueda por criterio
if (!empty($criterio)) {
    $criterio_lower = strtolower($criterio);
    $equipos = array_filter($equipos, function($e) use ($filtro, $criterio_lower) {
        if ($filtro == 'todos' || empty($filtro)) {
            // Buscar en TODOS los campos
            $campos = ['inventario', 'activo', 'responsable', 'ubicacion', 'cc', 'marca', 'modelo', 'serie', 'tipo', 'Nivel'];
            foreach ($campos as $campo) {
                $valor = strtolower($e[$campo] ?? '');
                if (strpos($valor, $criterio_lower) !== false) {
                    return true;
                }
            }
            return false;
        } else {
            // Buscar en un campo específico
            $valor = strtolower($e[$filtro] ?? '');
            return strpos($valor, $criterio_lower) !== false;
        }
    });
    $equipos = array_values($equipos);
}

if (empty($equipos) && !empty($criterio)) {
    $mensaje = 'No se encontraron resultados para "' . htmlspecialchars($criterio) . '"';
}

// ============================================
// CONTADORES
// ============================================
$totalPC = $db->fetchOne("SELECT COUNT(*) as total FROM t_inventpc")['total'] ?? 0;
$totalImp = $db->fetchOne("SELECT COUNT(*) as total FROM t_impresores")['total'] ?? 0;
$totalUps = $db->fetchOne("SELECT COUNT(*) as total FROM t_ups")['total'] ?? 0;
$totalOtros = $db->fetchOne("SELECT COUNT(*) as total FROM t_otros")['total'] ?? 0;
$totalGeneral = $totalPC + $totalImp + $totalUps + $totalOtros;

$nombreTipo = [
    'todos' => 'Todos los Equipos',
    'pc' => 'Computadoras',
    'impresora' => 'Impresoras',
    'ups' => 'UPS',
    'otros' => 'Otros'
];
$titulo = $nombreTipo[$tipo] ?? 'Todos los Equipos';
$nombre_filtro = match($filtro) {
    'inventario' => 'Inventario',
    'activo' => 'Activo',
    'responsable' => 'Responsable',
    'ubicacion' => 'Ubicación',
    'cc' => 'Centro de Costo',
    'marca' => 'Marca',
    'modelo' => 'Modelo',
    'serie' => 'Serie',
    default => 'Todos los campos'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php"><i class="bi bi-boxes"></i> SIR</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../../index.php"><i class="bi bi-house"></i> Inicio</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- ENCABEZADO -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-laptop"></i> <?= $titulo ?></h3>
            <div>
                <a href="buscar.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Nuevo Equipo
                </a>
                <a href="descargar.php?tipo=<?= $tipo ?>&filtro=<?= urlencode($filtro) ?>&criterio=<?= urlencode($criterio) ?>" 
                   class="btn btn-primary">
                    <i class="bi bi-download"></i> Descargar Excel
                </a>
                <button class="btn btn-outline-secondary" onclick="window.location.reload();">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                </button>
            </div>
        </div>

        <!-- CONTADORES -->
        <div class="row mb-3">
            <div class="col-md-2 col-6"><div class="card text-white bg-primary"><div class="card-body py-2"><small>Total</small><h5 class="mb-0"><?= $totalGeneral ?></h5></div></div></div>
            <div class="col-md-2 col-6"><div class="card text-white bg-info"><div class="card-body py-2"><small>PCs</small><h5 class="mb-0"><?= $totalPC ?></h5></div></div></div>
            <div class="col-md-2 col-6"><div class="card text-white bg-warning"><div class="card-body py-2"><small>Impresoras</small><h5 class="mb-0"><?= $totalImp ?></h5></div></div></div>
            <div class="col-md-2 col-6"><div class="card text-white bg-secondary"><div class="card-body py-2"><small>UPS</small><h5 class="mb-0"><?= $totalUps ?></h5></div></div></div>
            <div class="col-md-2 col-6"><div class="card text-white bg-danger"><div class="card-body py-2"><small>Otros</small><h5 class="mb-0"><?= $totalOtros ?></h5></div></div></div>
        </div>

        <!-- FILTROS UNIFICADOS -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end" id="filtroForm">
                    <div class="col-md-2">
                        <label class="form-label small">Tipo:</label>
                        <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
                            <option value="todos" <?= $tipo=='todos'?'selected':'' ?>>Todos</option>
                            <option value="pc" <?= $tipo=='pc'?'selected':'' ?>>PC</option>
                            <option value="impresora" <?= $tipo=='impresora'?'selected':'' ?>>Impresoras</option>
                            <option value="ups" <?= $tipo=='ups'?'selected':'' ?>>UPS</option>
                            <option value="otros" <?= $tipo=='otros'?'selected':'' ?>>Otros</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Buscar por:</label>
                        <select class="form-select form-select-sm" name="filtro">
                            <option value="todos" <?= ($filtro == 'todos' || empty($filtro)) ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="inventario" <?= $filtro=='inventario'?'selected':'' ?>>Inventario</option>
                            <option value="activo" <?= $filtro=='activo'?'selected':'' ?>>Activo</option>
                            <option value="responsable" <?= $filtro=='responsable'?'selected':'' ?>>Responsable</option>
                            <option value="ubicacion" <?= $filtro=='ubicacion'?'selected':'' ?>>Ubicación</option>
                            <option value="cc" <?= $filtro=='cc'?'selected':'' ?>>CC</option>
                            <option value="marca" <?= $filtro=='marca'?'selected':'' ?>>Marca</option>
                            <option value="modelo" <?= $filtro=='modelo'?'selected':'' ?>>Modelo</option>
                            <option value="serie" <?= $filtro=='serie'?'selected':'' ?>>Serie</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Criterio:</label>
                        <input type="text" class="form-control form-control-sm" name="criterio" id="criterioInput"
                               placeholder="Buscar..." value="<?= htmlspecialchars($criterio) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="descargar.php?tipo=<?= $tipo ?>&filtro=<?= urlencode($filtro) ?>&criterio=<?= urlencode($criterio) ?>" 
                           class="btn btn-success btn-sm w-100">
                            <i class="bi bi-download"></i> Descargar
                        </a>
                    </div>
                    <?php if (!empty($criterio)): ?>
                    <div class="col-12 text-end">
                        <a href="?tipo=<?= $tipo ?>" class="btn btn-link btn-sm text-danger">
                            <i class="bi bi-eraser"></i> Limpiar filtros
                        </a>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">
                            Mostrando resultados para "<strong><?= htmlspecialchars($criterio) ?></strong>" 
                            en <strong><?= $nombre_filtro ?></strong>
                            (<?= count($equipos) ?> resultados)
                        </small>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- MENSAJE -->
        <?php if ($mensaje): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- TABLA -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="inventarioTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Inventario</th>
                                <th>Activo</th>
                                <th>CC</th>
                                <th>Responsable</th>
                                <th>Ubicación</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($equipos)): ?>
                                <?php foreach ($equipos as $e): ?>
                                <tr>
                                    <td><span class="badge bg-<?= $e['tipo_equipo']=='PC'?'info':($e['tipo_equipo']=='IMPRESORA'?'warning':($e['tipo_equipo']=='UPS'?'secondary':'danger')) ?>"><?= $e['tipo_equipo'] ?></span></td>
                                    <td><strong><?= htmlspecialchars($e['inventario']) ?></strong></td>
                                    <td>
                                        <?php 
                                        $activo = $e['activo'] ?? '';
                                        if (empty($activo) || $activo === '0') {
                                            echo '<span class="text-muted">N/A</span>';
                                        } else {
                                            echo '<span class="badge bg-info">' . htmlspecialchars($activo) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($e['cc'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($e['responsable'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($e['ubicacion'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($e['marca'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($e['modelo'] ?? '') ?></td>
                                    <td><span class="badge bg-success">Activo</span></td>
                                    <td>
                                        <a href="detalle.php?id=<?= urlencode($e['inventario']) ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                                        <a href="editar.php?id=<?= urlencode($e['inventario']) ?>&tipo=<?= urlencode($e['tipo_equipo']) ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                        <a href="eliminar.php?id=<?= urlencode($e['inventario']) ?>&tipo=<?= urlencode($e['tipo_equipo']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">No hay equipos registrados</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($equipos) && !empty($criterio)): ?>
                    <small class="text-muted">Mostrando <?= count($equipos) ?> equipos (filtrados por <?= $nombre_filtro ?>)</small>
                <?php elseif (!empty($equipos)): ?>
                    <small class="text-muted">Mostrando <?= count($equipos) ?> equipos</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        <?php if (!empty($equipos)): ?>
        var table = $('#inventarioTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            order: [[0, 'asc']],
            pageLength: 10,
            columnDefs: [{ orderable: false, targets: [9] }],
            searching: true
        });
        
        $('#criterioInput').on('keyup', function() {
            table.search(this.value).draw();
        });
        
        $('#filtroForm').on('submit', function(e) {
            var valor = $('#criterioInput').val();
            table.search(valor).draw();
            e.preventDefault();
            return false;
        });
        
        $('#criterioInput').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                table.search(this.value).draw();
            }
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>