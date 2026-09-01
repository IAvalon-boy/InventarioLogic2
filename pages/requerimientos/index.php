<?php
header('Content-Type: text/html; charset=utf-8');
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
$filtro = $_GET['filtro'] ?? '';
$criterio = $_GET['criterio'] ?? '';
$mensaje = '';

// ============================================
// CONSTRUIR CONSULTA CON FILTROS UNIFICADOS
// ============================================
$sql = "SELECT r.*, 
               COALESCE(pc.activo, i.activo, u.activo, o.activo) as activo
        FROM t_requerimiento r
        LEFT JOIN t_inventpc pc ON r.inventario = pc.inventario
        LEFT JOIN t_impresores i ON r.inventario = i.inventario
        LEFT JOIN t_ups u ON r.inventario = u.inventario
        LEFT JOIN t_otros o ON r.inventario = o.inventario";

// ✅ BÚSQUEDA UNIFICADA
if (!empty($criterio)) {
    $criterio_escape = addslashes($criterio);
    
    if (!empty($filtro) && $filtro != 'todos') {
        // Buscar en un campo específico
        $campos_validos = ['inventario', 'responsable', 'cc', 'falla', 'estatus'];
        if (in_array($filtro, $campos_validos)) {
            $sql .= " WHERE r.$filtro LIKE '%$criterio_escape%'";
        } else {
            // Si el filtro no es válido, buscar en todos
            $sql .= " WHERE (r.inventario LIKE '%$criterio_escape%'
                       OR r.responsable LIKE '%$criterio_escape%'
                       OR r.cc LIKE '%$criterio_escape%'
                       OR r.falla LIKE '%$criterio_escape%'
                       OR r.estatus LIKE '%$criterio_escape%')";
        }
    } else {
        // Buscar en TODOS los campos
        $sql .= " WHERE (r.inventario LIKE '%$criterio_escape%'
                   OR r.responsable LIKE '%$criterio_escape%'
                   OR r.cc LIKE '%$criterio_escape%'
                   OR r.falla LIKE '%$criterio_escape%'
                   OR r.estatus LIKE '%$criterio_escape%'
                   OR r.ubicacion LIKE '%$criterio_escape%'
                   OR r.telefono LIKE '%$criterio_escape%'
                   OR r.tipo LIKE '%$criterio_escape%')";
    }
}

$sql .= " ORDER BY r.requerimiento DESC";

$requerimientos = $db->fetchAll($sql);

if (empty($requerimientos) && !empty($criterio)) {
    $mensaje = 'No se encontraron resultados para "' . htmlspecialchars($criterio) . '"';
}

// ============================================
// CONTADORES
// ============================================
$totalReq = $db->fetchOne("SELECT COUNT(*) as total FROM t_requerimiento")['total'] ?? 0;
$totalPendientes = $db->fetchOne("SELECT COUNT(*) as total FROM t_requerimiento WHERE estatus = 'PENDIENTE'")['total'] ?? 0;
$totalFinalizados = $db->fetchOne("SELECT COUNT(*) as total FROM t_requerimiento WHERE estatus = 'FINALIZADO'")['total'] ?? 0;

// Obtener el nombre del filtro para mostrar
$nombre_filtro = match($filtro) {
    'inventario' => 'Inventario',
    'responsable' => 'Responsable',
    'cc' => 'Centro de Costo',
    'falla' => 'Descripción',
    'estatus' => 'Estado',
    default => 'Todos los campos'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requerimientos - SIR</title>
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
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h3><i class="bi bi-clipboard"></i> Requerimientos</h3>
            <div>
                <a href="nuevo.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Nuevo Requerimiento
                </a>
                <button class="btn btn-outline-secondary" onclick="window.location.reload();">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                </button>
            </div>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="row mb-3">
            <div class="col-md-4 col-6">
                <div class="card text-white bg-primary"><div class="card-body py-2"><small>Total</small><h5 class="mb-0"><?= $totalReq ?></h5></div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card text-white bg-warning"><div class="card-body py-2"><small>Pendientes</small><h5 class="mb-0"><?= $totalPendientes ?></h5></div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card text-white bg-success"><div class="card-body py-2"><small>Finalizados</small><h5 class="mb-0"><?= $totalFinalizados ?></h5></div></div>
            </div>
        </div>

        <!-- FILTROS UNIFICADOS -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end" id="filtroForm">
                    <div class="col-md-2">
                        <label class="form-label small">Buscar por:</label>
                        <select class="form-select form-select-sm" name="filtro">
                            <option value="todos" <?= ($filtro == 'todos' || empty($filtro)) ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="inventario" <?= $filtro == 'inventario' ? 'selected' : '' ?>>Inventario</option>
                            <option value="responsable" <?= $filtro == 'responsable' ? 'selected' : '' ?>>Responsable</option>
                            <option value="cc" <?= $filtro == 'cc' ? 'selected' : '' ?>>Centro de Costo</option>
                            <option value="falla" <?= $filtro == 'falla' ? 'selected' : '' ?>>Descripción</option>
                            <option value="estatus" <?= $filtro == 'estatus' ? 'selected' : '' ?>>Estado</option>
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
                        <a href="descargar_filtro.php?tipo=REQ&filtro=<?= urlencode($filtro) ?>&criterio=<?= urlencode($criterio) ?>" 
                           class="btn btn-success btn-sm w-100">
                            <i class="bi bi-download"></i> Descargar
                        </a>
                    </div>
                    <div class="col-md-2">
                        <?php if (!empty($criterio)): ?>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-eraser"></i> Limpiar
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($criterio)): ?>
                    <div class="col-12">
                        <small class="text-muted">
                            Mostrando resultados para "<strong><?= htmlspecialchars($criterio) ?></strong>" 
                            en <strong><?= $nombre_filtro ?></strong>
                            (<?= count($requerimientos) ?> resultados)
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
                    <table id="requerimientosTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Inventario</th>
                                <th>Activo</th>
                                <th>CC</th>
                                <th>Responsable</th>
                                <th>Falla</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requerimientos)): ?>
                                <?php foreach ($requerimientos as $req): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($req['requerimiento']) ?></strong></td>
                                    <td><?= htmlspecialchars($req['inventario']) ?></td>
                                    <td><?= htmlspecialchars($req['activo'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($req['cc']) ?></td>
                                    <td><?= htmlspecialchars($req['responsable']) ?></td>
                                    <td><?= htmlspecialchars(substr($req['falla'], 0, 50)) . (strlen($req['falla']) > 50 ? '...' : '') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $req['estatus'] == 'FINALIZADO' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($req['estatus'] ?? 'PENDIENTE') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($req['insertdate']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="editar.php?id=<?= $req['requerimiento'] ?>" class="btn btn-outline-warning"><i class="bi bi-pencil"></i></a>
                                            <a href="eliminar.php?id=<?= $req['requerimiento'] ?>" class="btn btn-outline-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">No hay requerimientos registrados</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($requerimientos) && !empty($criterio)): ?>
                    <small class="text-muted">Mostrando <?= count($requerimientos) ?> requerimientos (filtrados por <?= $nombre_filtro ?>)</small>
                <?php elseif (!empty($requerimientos)): ?>
                    <small class="text-muted">Mostrando <?= count($requerimientos) ?> requerimientos</small>
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
        <?php if (!empty($requerimientos)): ?>
        $('#requerimientosTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            order: [[0, 'desc']],
            pageLength: 10,
            columnDefs: [{ orderable: false, targets: [8] }]
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>