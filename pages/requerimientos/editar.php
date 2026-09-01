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
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'falla' => strtoupper($_POST['falla']),
        'servicio' => $_POST['servicio'] ?? null,
        'atencion' => $_POST['atencion'] ?? null,
        'estatus' => $_POST['estatus']
    ];
    
    try {
        $db->update('t_requerimiento', $data, 'requerimiento = ?', [$id]);
        $success = 'Requerimiento actualizado exitosamente';
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
    } catch (Exception $e) {
        $error = 'Error al actualizar: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Requerimiento - SIR</title>
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
        <h3><i class="bi bi-pencil-square"></i> Editar Requerimiento #<?= htmlspecialchars($req['requerimiento']) ?></h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Inventario</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($req['inventario']) ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Activo</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($req['activo'] ?? 'N/A') ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Responsable</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($req['responsable']) ?>" disabled>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Descripción de la Falla *</label>
                                <textarea class="form-control" name="falla" rows="3" required><?= htmlspecialchars($req['falla']) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Servicio</label>
                                <select class="form-select" name="servicio">
                                    <option value="">Seleccionar...</option>
                                    <option value="SOFTWARE" <?= $req['servicio'] == 'SOFTWARE' ? 'selected' : '' ?>>Software</option>
                                    <option value="HARDWARE" <?= $req['servicio'] == 'HARDWARE' ? 'selected' : '' ?>>Hardware</option>
                                    <option value="REDES" <?= $req['servicio'] == 'REDES' ? 'selected' : '' ?>>Redes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Atención</label>
                                <select class="form-select" name="atencion">
                                    <option value="">Seleccionar...</option>
                                    <option value="PRESENCIAL" <?= $req['atencion'] == 'PRESENCIAL' ? 'selected' : '' ?>>Presencial</option>
                                    <option value="TELEFONICA" <?= $req['atencion'] == 'TELEFONICA' ? 'selected' : '' ?>>Telefónica</option>
                                    <option value="REMOTA" <?= $req['atencion'] == 'REMOTA' ? 'selected' : '' ?>>Remota</option>
                                    <option value="PROCESO EN SISTEMA" <?= $req['atencion'] == 'PROCESO EN SISTEMA' ? 'selected' : '' ?>>Proceso en sistema</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Estado *</label>
                                <select class="form-select" name="estatus" required>
                                    <option value="PENDIENTE" <?= $req['estatus'] == 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="FINALIZADO" <?= $req['estatus'] == 'FINALIZADO' ? 'selected' : '' ?>>Finalizado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Actualizar
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