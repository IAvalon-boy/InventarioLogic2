<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$resultados = [];
$tipo = $_GET['tipo'] ?? '';
$filtro = $_GET['filtro'] ?? '';
$criterio = $_GET['criterio'] ?? '';

if (!empty($tipo)) {
    switch ($tipo) {
        case 'PC':
            $table = 't_inventpc';
            break;
        case 'IMP':
            $table = 't_impresores';
            break;
        case 'UPS':
            $table = 't_ups';
            break;
        case 'OTROS':
            $table = 't_otros';
            break;
        default:
            $table = '';
    }
    
    if (!empty($table)) {
        $sql = "SELECT * FROM $table WHERE estadoEquipo = 1";
        if (!empty($filtro) && !empty($criterio)) {
            $sql .= " AND $filtro LIKE '%$criterio%'";
        }
        $sql .= " ORDER BY Nivel";
        $resultados = $db->fetchAll($sql);
    }
}

// Obtener usuarios para reportes de requerimientos
$usuarios = $db->fetchAll("SELECT DISTINCT Insertuser FROM t_requerimiento ORDER BY Insertuser");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">
                <i class="bi bi-boxes"></i> SIR
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../../index.php">
                            <i class="bi bi-house"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <h3><i class="bi bi-file-earmark-spreadsheet"></i> Generador de Reportes</h3>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Reporte de Equipos</div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Equipo *</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="PC">Computadoras</option>
                                    <option value="IMP">Impresoras</option>
                                    <option value="UPS">UPS</option>
                                    <option value="OTROS">Otros</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Filtro</label>
                                <select class="form-select" name="filtro">
                                    <option value="">Sin filtro</option>
                                    <option value="cc">Centro de Costo</option>
                                    <option value="marca">Marca</option>
                                    <option value="modelo">Modelo</option>
                                    <option value="ubicacion">Ubicación</option>
                                    <option value="Nivel">Nivel</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Criterio</label>
                                <input type="text" class="form-control" name="criterio" placeholder="Valor a buscar">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Generar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list"></i> Resultados</span>
                        <?php if (!empty($resultados)): ?>
                            <a href="descargar.php?tipo=<?= urlencode($tipo) ?>&filtro=<?= urlencode($filtro) ?>&criterio=<?= urlencode($criterio) ?>" 
                               class="btn btn-success btn-sm">
                                <i class="bi bi-download"></i> Descargar Excel
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($resultados)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Inventario</th>
                                            <th>Activo</th>
                                            <th>CC</th>
                                            <th>Responsable</th>
                                            <th>Ubicación</th>
                                            <th>Marca</th>
                                            <th>Modelo</th>
                                            <th>Nivel</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultados as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['inventario'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['activo'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($row['cc'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['responsable'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ubicacion'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['marca'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['modelo'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['Nivel'] ?? '') ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($row['estadoEquipo'] ?? 0) == 1 ? 'success' : 'danger' ?>">
                                                    <?= ($row['estadoEquipo'] ?? 0) == 1 ? 'Activo' : 'Inactivo' ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted mt-2">Total: <?= count($resultados) ?> registros</p>
                        <?php else: ?>
                            <p class="text-muted text-center">Seleccione los criterios y presione "Generar"</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reporte de Requerimientos -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Reporte de Requerimientos por Fechas</div>
                    <div class="card-body">
                        <form method="GET" action="descargar.php" target="_blank">
                            <input type="hidden" name="tipo" value="REQ">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha Inicio *</label>
                                        <input type="date" class="form-control" name="fecha_ini" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha Fin *</label>
                                        <input type="date" class="form-control" name="fecha_fin" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Usuario</label>
                                        <select class="form-select" name="usuario">
                                            <option value="">Todos</option>
                                            <?php foreach ($usuarios as $u): ?>
                                                <option value="<?= htmlspecialchars($u['Insertuser']) ?>">
                                                    <?= htmlspecialchars($u['Insertuser']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-download"></i> Descargar Reporte
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>
</html>