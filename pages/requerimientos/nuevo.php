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
$equipo = null;

// Buscar equipo por inventario o activo
$busqueda = $_GET['busqueda'] ?? '';

if (!empty($busqueda)) {
    // Buscar en todas las tablas por inventario O activo
    $equipo = $db->fetchOne(
        "SELECT * FROM t_inventpc WHERE inventario = ? OR activo = ?",
        [$busqueda, $busqueda]
    );
    if (!$equipo) {
        $equipo = $db->fetchOne(
            "SELECT * FROM t_impresores WHERE inventario = ? OR activo = ?",
            [$busqueda, $busqueda]
        );
    }
    if (!$equipo) {
        $equipo = $db->fetchOne(
            "SELECT * FROM t_ups WHERE inventario = ? OR activo = ?",
            [$busqueda, $busqueda]
        );
    }
    if (!$equipo) {
        $equipo = $db->fetchOne(
            "SELECT * FROM t_otros WHERE inventario = ? OR activo = ?",
            [$busqueda, $busqueda]
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Session::get('user_id');
    $nivel = Session::get('user_level');
    
    $data = [
        'inventario' => $_POST['inventario'],
        'cc' => $_POST['cc'],
        'responsable' => strtoupper($_POST['responsable']),
        'ubicacion' => strtoupper($_POST['ubicacion']),
        'telefono' => $_POST['telefono'],
        'tipo' => $_POST['tipo'],
        'falla' => $_POST['falla'],
        'estatus' => $nivel == 1 ? 'FINALIZADO' : 'PENDIENTE',
        'servicio' => $_POST['servicio'] ?? null,
        'atencion' => $_POST['atencion'] ?? null,
        'Insertuser' => $user,
        'insertdate' => date('Y-m-d')
    ];
    
    try {
        $db->insert('t_requerimiento', $data);
        $success = 'Requerimiento registrado exitosamente';
        // Resetear equipo después de guardar
        $equipo = null;
        $busqueda = '';
    } catch (Exception $e) {
        $error = 'Error al registrar: ' . $e->getMessage();
    }
}

// Obtener último número de requerimiento
$lastReq = $db->fetchOne("SELECT MAX(requerimiento) as max FROM t_requerimiento");
$nextReq = ($lastReq['max'] ?? 0) + 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Requerimiento - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">
                <i class="bi bi-boxes"></i> SIbD
            </a>
            <a class="btn btn-outline-light" href="index.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3><i class="bi bi-plus-circle"></i> Nuevo Requerimiento</h3>
        <p class="text-muted">Número de requerimiento: <strong>#<?= $nextReq ?></strong></p>
        
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
                        <!-- Buscar equipo por inventario o activo -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Buscar por Inventario o Activo *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="busqueda" id="busqueda" 
                                           value="<?= htmlspecialchars($busqueda) ?>" 
                                           placeholder="Ingrese número de inventario o activo" required>
                                    <button class="btn btn-outline-secondary" type="button" id="buscarEquipo">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                                <?php if ($equipo): ?>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle"></i> Equipo encontrado: 
                                        <strong><?= htmlspecialchars($equipo['inventario']) ?></strong>
                                        <?php if (!empty($equipo['activo'])): ?>
                                            (Activo: <strong><?= htmlspecialchars($equipo['activo']) ?></strong>)
                                        <?php endif; ?>
                                    </small>
                                <?php elseif ($busqueda && !$equipo): ?>
                                    <small class="text-danger">
                                        <i class="bi bi-exclamation-circle"></i> No se encontró equipo con ese número
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Campos ocultos del equipo -->
                        <input type="hidden" name="inventario" id="inventario" value="<?= htmlspecialchars($equipo['inventario'] ?? '') ?>">
                        <input type="hidden" name="cc" id="cc" value="<?= htmlspecialchars($equipo['cc'] ?? '') ?>">
                        <input type="hidden" name="responsable" id="responsable" value="<?= htmlspecialchars($equipo['responsable'] ?? '') ?>">
                        <input type="hidden" name="ubicacion" id="ubicacion" value="<?= htmlspecialchars($equipo['ubicacion'] ?? '') ?>">
                        <input type="hidden" name="telefono" id="telefono" value="<?= htmlspecialchars($equipo['telefono'] ?? '') ?>">
                        <input type="hidden" name="tipo" id="tipo" value="<?= htmlspecialchars($equipo['tipo'] ?? '') ?>">
                        
                        <!-- Mostrar datos del equipo -->
                        <div class="col-md-6">
                            <?php if ($equipo): ?>
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th style="width:100px;">Inventario:</th>
                                            <td><strong><?= htmlspecialchars($equipo['inventario']) ?></strong></td>
                                        </tr>
                                        <?php if (!empty($equipo['activo'])): ?>
                                        <tr>
                                            <th>Activo:</th>
                                            <td><strong><?= htmlspecialchars($equipo['activo']) ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Responsable:</th>
                                            <td><?= htmlspecialchars($equipo['responsable'] ?? '') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Ubicación:</th>
                                            <td><?= htmlspecialchars($equipo['ubicacion'] ?? '') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Teléfono:</th>
                                            <td><?= htmlspecialchars($equipo['telefono'] ?? '') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tipo:</th>
                                            <td><?= htmlspecialchars($equipo['tipo'] ?? '') ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Descripción de la falla -->
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Descripción de la Falla *</label>
                                <textarea class="form-control" name="falla" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <?php if (Session::get('user_level') == 1): ?>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Servicio</label>
                                <select class="form-select" name="servicio">
                                    <option value="">Seleccionar...</option>
                                    <option value="SOFTWARE">Software</option>
                                    <option value="HARDWARE">Hardware</option>
                                    <option value="REDES">Redes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Atención</label>
                                <select class="form-select" name="atencion">
                                    <option value="">Seleccionar...</option>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="TELEFONICA">Telefónica</option>
                                    <option value="REMOTA">Remota</option>
                                    <option value="PROCESO EN SISTEMA">Proceso en sistema</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estatus">
                                    <option value="FINALIZADO">Finalizado</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" <?= !$equipo ? 'disabled' : '' ?>>
                        <i class="bi bi-save"></i> Guardar
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Buscar equipo
        document.getElementById('buscarEquipo').addEventListener('click', function() {
            var busqueda = document.getElementById('busqueda').value;
            if (busqueda) {
                window.location.href = 'nuevo.php?busqueda=' + encodeURIComponent(busqueda);
            }
        });
        
        document.getElementById('busqueda').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('buscarEquipo').click();
            }
        });
    </script>
</body>
</html>