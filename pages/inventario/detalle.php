<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Obtener ID del equipo
$id = $_GET['id'] ?? '';

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// ============================================
// 1. BUSCAR EL EQUIPO EN TODAS LAS TABLAS
// ============================================
$equipo = null;
$tipo_tabla = '';
$tipo_equipo = '';

// Buscar en PCs
$equipo = $db->fetchOne("SELECT * FROM t_inventpc WHERE inventario = ?", [$id]);
if ($equipo) {
    $tipo_tabla = 't_inventpc';
    $tipo_equipo = 'PC';
    $titulo_tipo = 'Computadora';
    $icono = 'bi-laptop';
    $color = 'info';
}

// Buscar en Impresoras
if (!$equipo) {
    $equipo = $db->fetchOne("SELECT * FROM t_impresores WHERE inventario = ?", [$id]);
    if ($equipo) {
        $tipo_tabla = 't_impresores';
        $tipo_equipo = 'IMPRESORA';
        $titulo_tipo = 'Impresora';
        $icono = 'bi-printer';
        $color = 'warning';
    }
}

// Buscar en UPS
if (!$equipo) {
    $equipo = $db->fetchOne("SELECT * FROM t_ups WHERE inventario = ?", [$id]);
    if ($equipo) {
        $tipo_tabla = 't_ups';
        $tipo_equipo = 'UPS';
        $titulo_tipo = 'UPS';
        $icono = 'bi-battery-charging';
        $color = 'secondary';
    }
}

// Buscar en Otros
if (!$equipo) {
    $equipo = $db->fetchOne("SELECT * FROM t_otros WHERE inventario = ?", [$id]);
    if ($equipo) {
        $tipo_tabla = 't_otros';
        $tipo_equipo = 'OTROS';
        $titulo_tipo = 'Otros';
        $icono = 'bi-box';
        $color = 'danger';
    }
}

// Si no se encontró en ninguna tabla
if (!$equipo) {
    header('Location: index.php?error=no_encontrado');
    exit;
}

// ============================================
// 2. CAMPOS ESPECÍFICOS POR TIPO
// ============================================
$campos_especificos = [];

switch ($tipo_tabla) {
    case 't_inventpc':
        $campos_especificos = [
            'procesador' => 'Procesador',
            'ram' => 'RAM',
            'hdd' => 'Disco Duro',
            'cdrom' => 'CD/DVD',
            'so' => 'Sistema Operativo',
            'l_so' => 'Licencia SO',
            'office' => 'Office',
            'l_office' => 'Licencia Office',
            'sistemasIsss' => 'Sistemas Institucionales',
            'antivirus' => 'Antivirus',
            'nombreEquipo' => 'Nombre Equipo',
            'ip' => 'IP',
            'dominio' => 'Dominio',
            'msus' => 'MSUS',
            'wsus' => 'WSUS',
            'otrosSistemas' => 'Otros Sistemas',
            'user_dom' => 'Usuario Dominio',
            'id_partes' => 'ID Partes'
        ];
        break;
    case 't_impresores':
        $campos_especificos = [
            'user_dom' => 'Usuario Dominio'
        ];
        break;
    case 't_ups':
        $campos_especificos = [
            'capacidadSalida' => 'Capacidad de Salida',
            'numTomas' => 'Número de Tomas',
            'user_dom' => 'Usuario Dominio'
        ];
        break;
    case 't_otros':
        $campos_especificos = [];
        break;
}

// ============================================
// 3. ESTADO DEL EQUIPO (CORREGIDO)
// ============================================
$estadoRaw = $equipo['estadoEquipo'] ?? '';

// Si es número, convertirlo a texto
if (is_numeric($estadoRaw)) {
    $estadoTexto = match((int)$estadoRaw) {
        1 => 'Activo',
        2 => 'En Mantenimiento',
        default => 'Inactivo'
    };
    $estadoColor = match((int)$estadoRaw) {
        1 => 'success',
        2 => 'warning',
        default => 'danger'
    };
} else {
    // Si ya es texto, normalizarlo
    $estadoNormalizado = strtolower(trim($estadoRaw));
    $estadoTexto = match($estadoNormalizado) {
        'activo', '1' => 'Activo',
        'mantenimiento', '2' => 'En Mantenimiento',
        default => 'Inactivo'
    };
    $estadoColor = match($estadoNormalizado) {
        'activo', '1' => 'success',
        'mantenimiento', '2' => 'warning',
        default => 'danger'
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle <?= $titulo_tipo ?> - SIR</title>
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
        <h3><i class="bi <?= $icono ?>"></i> Detalle del Equipo</h3>
        
        <div class="card">
            <div class="card-header bg-<?= $color ?> text-white d-flex justify-content-between align-items-center">
                <span><i class="bi <?= $icono ?>"></i> <?= $titulo_tipo ?> - <?= htmlspecialchars($equipo['inventario']) ?></span>
                <span class="badge bg-<?= $estadoColor ?>"><?= $estadoTexto ?></span>
            </div>
            <div class="card-body">
                <!-- ========================================== -->
                <!-- FILA 1: DATOS GENERALES                     -->
                <!-- ========================================== -->
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <?php
                            $campos_fila1 = ['inventario', 'activo', 'cc', 'responsable', 'ubicacion', 'telefono'];
                            foreach ($campos_fila1 as $campo):
                                if (!isset($equipo[$campo])) continue;
                                $label = match($campo) {
                                    'inventario' => 'Inventario',
                                    'activo' => 'Activo',
                                    'cc' => 'Centro de Costo',
                                    'responsable' => 'Responsable',
                                    'ubicacion' => 'Ubicación',
                                    'telefono' => 'Teléfono',
                                    default => ucfirst($campo)
                                };
                                $valor = $equipo[$campo] ?? 'N/A';
                                if ($campo == 'estadoEquipo') continue;
                            ?>
                            <tr>
                                <th style="width:150px;"><?= $label ?></th>
                                <td>
                                    <?php if ($campo == 'activo' && empty($valor)): ?>
                                        <span class="text-muted">N/A</span>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($valor) ?></strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>Tipo</th>
                                <td><span class="badge bg-<?= $color ?>"><?= $tipo_equipo ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <?php
                            $campos_fila2 = ['tipo', 'marca', 'modelo', 'serie', 'f_compra', 'venc_garantia'];
                            foreach ($campos_fila2 as $campo):
                                if (!isset($equipo[$campo])) continue;
                                $label = match($campo) {
                                    'tipo' => 'Tipo de Equipo',
                                    'marca' => 'Marca',
                                    'modelo' => 'Modelo',
                                    'serie' => 'Serie',
                                    'f_compra' => 'Fecha Compra',
                                    'venc_garantia' => 'Venc. Garantía',
                                    default => ucfirst($campo)
                                };
                                $valor = $equipo[$campo] ?? 'N/A';
                            ?>
                            <tr>
                                <th style="width:150px;"><?= $label ?></th>
                                <td><?= htmlspecialchars($valor) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>Nivel</th>
                                <td><?= htmlspecialchars($equipo['Nivel'] ?? 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- FILA 2: CAMPOS ESPECÍFICOS                  -->
                <!-- ========================================== -->
                <?php if (!empty($campos_especificos)): ?>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h5 class="text-<?= $color ?>">
                            <i class="bi bi-gear"></i> Especificaciones <?= $titulo_tipo ?>
                        </h5>
                        <table class="table table-bordered">
                            <?php foreach ($campos_especificos as $campo => $label): ?>
                                <?php if (isset($equipo[$campo]) && !empty($equipo[$campo])): ?>
                                <tr>
                                    <th style="width:150px;"><?= $label ?></th>
                                    <td><?= htmlspecialchars($equipo[$campo]) ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- FILA 3: AUDITORÍA                          -->
                <!-- ========================================== -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width:150px;">Usuario Creación</th>
                                <td><?= htmlspecialchars($equipo['insertuser'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th>Fecha Creación</th>
                                <td><?= htmlspecialchars($equipo['insertdate'] ?? 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <?php if (!empty($equipo['update_usr']) || !empty($equipo['updateuser'])): ?>
                            <tr>
                                <th style="width:150px;">Última Actualización</th>
                                <td>
                                    <?= htmlspecialchars($equipo['update_usr'] ?? $equipo['updateuser'] ?? 'N/A') ?>
                                    <?php if (!empty($equipo['updatedate'])): ?>
                                        - <?= htmlspecialchars($equipo['updatedate']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Tabla</th>
                                <td><span class="badge bg-<?= $color ?>"><?= $tipo_tabla ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="editar.php?id=<?= urlencode($equipo['inventario']) ?>&tipo=<?= urlencode($tipo_equipo) ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <a href="eliminar.php?id=<?= urlencode($equipo['inventario']) ?>&tipo=<?= urlencode($tipo_equipo) ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este equipo?')">
                    <i class="bi bi-trash"></i> Eliminar
                </a>
                <a href="index.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>
</html>