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
$tipo = $_GET['tipo'] ?? '';

// ============================================
// DETECTAR TABLA AUTOMÁTICAMENTE
// ============================================
if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Buscar en todas las tablas para detectar el tipo
$equipo = $db->fetchOne("SELECT * FROM t_inventpc WHERE inventario = ?", [$id]);
if ($equipo) {
    $tabla = 't_inventpc';
    $tipo = 'PC';
} else {
    $equipo = $db->fetchOne("SELECT * FROM t_impresores WHERE inventario = ?", [$id]);
    if ($equipo) {
        $tabla = 't_impresores';
        $tipo = 'IMPRESORA';
    } else {
        $equipo = $db->fetchOne("SELECT * FROM t_ups WHERE inventario = ?", [$id]);
        if ($equipo) {
            $tabla = 't_ups';
            $tipo = 'UPS';
        } else {
            $equipo = $db->fetchOne("SELECT * FROM t_otros WHERE inventario = ?", [$id]);
            if ($equipo) {
                $tabla = 't_otros';
                $tipo = 'OTROS';
            } else {
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Si no se encontró equipo en ninguna tabla
if (!$equipo) {
    header('Location: index.php?msg=not_found');
    exit;
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        // Verificar si tiene relaciones en requerimientos
        $requerimientos = $db->fetchOne(
            "SELECT COUNT(*) as total FROM t_requerimiento WHERE inventario = ?",
            [$id]
        );
        
        if ($requerimientos && $requerimientos['total'] > 0) {
            $error = 'No se puede eliminar este equipo porque tiene ' . $requerimientos['total'] . ' requerimientos asociados.';
        } else {
            $db->delete($tabla, 'inventario = ?', [$id]);
            header('Location: index.php?msg=deleted');
            exit;
        }
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
    <title>Eliminar Equipo - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php"><i class="bi bi-boxes"></i> SIR</a>
            <a class="btn btn-outline-light" href="index.php"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3><i class="bi bi-trash"></i> Eliminar Equipo</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación
                <span class="badge bg-light text-dark ms-2"><?= $tipo ?></span>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle"></i>
                    <strong>¿Está seguro de que desea eliminar este equipo?</strong>
                    <br>Esta acción no se puede deshacer.
                </div>
                
                <table class="table table-bordered">
                    <tr>
                        <th style="width:150px;">Inventario</th>
                        <td><?= htmlspecialchars($equipo['inventario']) ?></td>
                    </tr>
                    <tr>
                        <th>Activo</th>
                        <td><?= htmlspecialchars($equipo['activo'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Responsable</th>
                        <td><?= htmlspecialchars($equipo['responsable']) ?></td>
                    </tr>
                    <tr>
                        <th>Ubicación</th>
                        <td><?= htmlspecialchars($equipo['ubicacion']) ?></td>
                    </tr>
                    <tr>
                        <th>Tipo</th>
                        <td><span class="badge bg-info"><?= $tipo ?></span></td>
                    </tr>
                    <tr>
                        <th>Estado</th>
                        <td>
                            <span class="badge bg-<?= $equipo['estadoEquipo'] == 1 ? 'success' : 'danger' ?>">
                                <?= $equipo['estadoEquipo'] == 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                    </tr>
                </table>
                
                <form method="POST">
                    <button type="submit" name="confirmar" class="btn btn-danger" onclick="return confirm('¿Eliminar definitivamente este equipo?')">
                        <i class="bi bi-trash"></i> Sí, Eliminar
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>