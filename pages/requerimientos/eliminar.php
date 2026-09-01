<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$error = '';

$id = $_GET['id'] ?? '';

if (empty($id)) {
    header('Location: index.php');
    exit;
}

$req = $db->fetchOne("
    SELECT r.*, 
           COALESCE(pc.activo, i.activo, u.activo, o.activo) as activo
    FROM t_requerimiento r
    LEFT JOIN t_inventpc pc ON r.inventario = pc.inventario
    LEFT JOIN t_impresores i ON r.inventario = i.inventario
    LEFT JOIN t_ups u ON r.inventario = u.inventario
    LEFT JOIN t_otros o ON r.inventario = o.inventario
    WHERE r.requerimiento = ?
", [$id]);

if (!$req) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        $db->delete('t_requerimiento', 'requerimiento = ?', [$id]);
        header('Location: index.php?msg=deleted');
        exit;
    } catch (Exception $e) {
        $error = 'Error al eliminar: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Requerimiento - SIR</title>
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
            <a class="btn btn-outline-light" href="index.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3><i class="bi bi-trash"></i> Eliminar Requerimiento</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle"></i>
                    <strong>¿Está seguro de que desea eliminar este requerimiento?</strong>
                    <br>Esta acción no se puede deshacer.
                </div>
                
                <table class="table table-bordered">
                    <tr>
                        <th style="width:150px;">Requerimiento</th>
                        <td><strong>#<?= htmlspecialchars($req['requerimiento']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Inventario</th>
                        <td><?= htmlspecialchars($req['inventario']) ?></td>
                    </tr>
                    <tr>
                        <th>Activo</th>
                        <td><?= htmlspecialchars($req['activo'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Responsable</th>
                        <td><?= htmlspecialchars($req['responsable']) ?></td>
                    </tr>
                    <tr>
                        <th>Falla</th>
                        <td><?= htmlspecialchars($req['falla']) ?></td>
                    </tr>
                    <tr>
                        <th>Estado</th>
                        <td>
                            <span class="badge bg-<?= $req['estatus'] == 'FINALIZADO' ? 'success' : 'warning' ?>">
                                <?= htmlspecialchars($req['estatus'] ?? 'PENDIENTE') ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Fecha</th>
                        <td><?= htmlspecialchars($req['insertdate']) ?></td>
                    </tr>
                </table>
                
                <form method="POST">
                    <button type="submit" name="confirmar" class="btn btn-danger" onclick="return confirm('¿Eliminar definitivamente este requerimiento?')">
                        <i class="bi bi-trash"></i> Sí, Eliminar
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>
</html>