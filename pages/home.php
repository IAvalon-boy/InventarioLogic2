<?php
require_once '../includes/session.php';
require_once '../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: auth/login.php');
    exit;
}

$db = Database::getInstance();
$user = Session::getUser();

// Contadores
$totalPc = $db->fetchOne("SELECT COUNT(*) as total FROM t_inventpc")['total'] ?? 0;
$totalImp = $db->fetchOne("SELECT COUNT(*) as total FROM t_impresores")['total'] ?? 0;
$totalUps = $db->fetchOne("SELECT COUNT(*) as total FROM t_ups")['total'] ?? 0;
$totalOtros = $db->fetchOne("SELECT COUNT(*) as total FROM t_otros")['total'] ?? 0;
$totalReq = $db->fetchOne("SELECT COUNT(*) as total FROM t_requerimiento WHERE estatus = 'PENDIENTE'")['total'] ?? 0;

// Últimos requerimientos CON ACTIVO
$ultimosReq = $db->fetchAll("
    SELECT r.*, 
           COALESCE(pc.activo, i.activo, u.activo, o.activo) as activo
    FROM t_requerimiento r
    LEFT JOIN t_inventpc pc ON r.inventario = pc.inventario
    LEFT JOIN t_impresores i ON r.inventario = i.inventario
    LEFT JOIN t_ups u ON r.inventario = u.inventario
    LEFT JOIN t_otros o ON r.inventario = o.inventario
    ORDER BY r.requerimiento DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-boxes"></i> SIR - Sistema de Inventario
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="auth/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-2">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action active">
                        <i class="bi bi-house"></i> Inicio
                    </a>
                    <a href="inventario/index.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-laptop"></i> Inventario
                    </a>
                    <a href="requerimientos/index.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clipboard"></i> Requerimientos
                    </a>
                    <a href="reportes/index.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Reportes
                    </a>
                    <?php if (Session::isAdmin()): ?>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="bi bi-people"></i> Usuarios
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Bienvenido, <?= htmlspecialchars($user['name']) ?></h2>
                    <span class="badge bg-primary">
                        <i class="bi bi-person"></i> Nivel <?= htmlspecialchars($user['level']) ?>
                    </span>
                </div>
                <p class="text-muted">Sistema de Control de Inventarios y Registro de Requerimientos</p>
                
                <div class="row dashboard-stats">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card stat-bg-primary">
                            <div class="stat-label"><i class="bi bi-laptop"></i> Computadoras</div>
                            <div class="stat-number"><?= $totalPc ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card stat-bg-success">
                            <div class="stat-label"><i class="bi bi-printer"></i> Impresoras</div>
                            <div class="stat-number"><?= $totalImp ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card stat-bg-warning">
                            <div class="stat-label"><i class="bi bi-battery-charging"></i> UPS</div>
                            <div class="stat-number"><?= $totalUps ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card stat-bg-danger">
                            <div class="stat-label"><i class="bi bi-box"></i> Otros</div>
                            <div class="stat-number"><?= $totalOtros ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card stat-bg-info">
                            <div class="stat-label"><i class="bi bi-clipboard"></i> Requerimientos Pendientes</div>
                            <div class="stat-number"><?= $totalReq ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Últimos Requerimientos
                    </div>
                    <div class="card-body">
                        <?php if (!empty($ultimosReq)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Inventario</th>
                                            <th>Activo</th>
                                            <th>Responsable</th>
                                            <th>Falla</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosReq as $req): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($req['requerimiento']) ?></td>
                                            <td><?= htmlspecialchars($req['inventario']) ?></td>
                                            <td><?= htmlspecialchars($req['activo'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($req['responsable']) ?></td>
                                            <td><?= htmlspecialchars(substr($req['falla'], 0, 40)) . (strlen($req['falla']) > 40 ? '...' : '') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $req['estatus'] == 'FINALIZADO' ? 'success' : 'warning' ?>">
                                                    <?= htmlspecialchars($req['estatus'] ?? 'PENDIENTE') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($req['insertdate']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No hay requerimientos registrados</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>