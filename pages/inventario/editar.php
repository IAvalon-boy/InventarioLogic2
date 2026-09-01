<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Obtener ID y tipo
$id = $_GET['id'] ?? '';
$tipo = $_GET['tipo'] ?? '';

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// ============================================
// 1. DETECTAR EL TIPO DE EQUIPO
// ============================================
$tabla = '';
$titulo_tipo = '';
$campos_especificos = [];

if ($tipo == 'PC') {
    $tabla = 't_inventpc';
    $titulo_tipo = 'PC';
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
} elseif ($tipo == 'IMPRESORA') {
    $tabla = 't_impresores';
    $titulo_tipo = 'Impresora';
    $campos_especificos = [
        'user_dom' => 'Usuario Dominio'
    ];
} elseif ($tipo == 'UPS') {
    $tabla = 't_ups';
    $titulo_tipo = 'UPS';
    $campos_especificos = [
        'capacidadSalida' => 'Capacidad de Salida',
        'numTomas' => 'Número de Tomas',
        'user_dom' => 'Usuario Dominio'
    ];
} elseif ($tipo == 'OTROS') {
    $tabla = 't_otros';
    $titulo_tipo = 'Otros';
    $campos_especificos = [];
} else {
    // Auto-detectar si no viene tipo
    $equipo = $db->fetchOne("SELECT * FROM t_inventpc WHERE inventario = ?", [$id]);
    if ($equipo) {
        $tabla = 't_inventpc';
        $titulo_tipo = 'PC';
        $tipo = 'PC';
    } else {
        $equipo = $db->fetchOne("SELECT * FROM t_impresores WHERE inventario = ?", [$id]);
        if ($equipo) {
            $tabla = 't_impresores';
            $titulo_tipo = 'Impresora';
            $tipo = 'IMPRESORA';
        } else {
            $equipo = $db->fetchOne("SELECT * FROM t_ups WHERE inventario = ?", [$id]);
            if ($equipo) {
                $tabla = 't_ups';
                $titulo_tipo = 'UPS';
                $tipo = 'UPS';
            } else {
                $equipo = $db->fetchOne("SELECT * FROM t_otros WHERE inventario = ?", [$id]);
                if ($equipo) {
                    $tabla = 't_otros';
                    $titulo_tipo = 'Otros';
                    $tipo = 'OTROS';
                }
            }
        }
    }
}

// Si no se encontró en ninguna tabla
if (empty($tabla)) {
    header('Location: index.php');
    exit;
}

// Cargar datos del equipo
$equipo = $db->fetchOne("SELECT * FROM $tabla WHERE inventario = ?", [$id]);

if (!$equipo) {
    header('Location: index.php');
    exit;
}

// Obtener centros de costo
$ccs = $db->fetchAll("SELECT * FROM t_cc ORDER BY cc");

// ✅ DESCRIPCIONES DE CENTROS DE COSTO (SOLO PARA VISTA)
$descripciones_cc = [
    '526101' => 'ADMINISTRACIÓN',
    '526102' => 'SERVICIOS GENERALES',
    '526103' => 'ALMACÉN',
    '526104' => 'LAVANDERÍA Y ROPERÍA',
    '526107' => 'ADMISION Y REGISTROS MEDICOS',
    '526115' => 'RECURSOS HUMANOS',
    '533A38' => 'SALUD MENTAL'
];

$error = '';
$success = '';

// ============================================
// 2. PROCESAR ACTUALIZACIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = Session::get('user_id');
    $hoy = date('Y-m-d');

    $data = [
        'cc' => $_POST['cc'],
        'responsable' => strtoupper($_POST['responsable']),
        'ubicacion' => strtoupper($_POST['ubicacion']),
        'telefono' => $_POST['telefono'],
        'tipo' => $_POST['tipo_equipo'] ?? $titulo_tipo,
        'marca' => $_POST['marca'],
        'modelo' => strtoupper($_POST['modelo']),
        'serie' => strtoupper($_POST['serie']),
        'f_compra' => !empty($_POST['f_compra']) ? $_POST['f_compra'] : null,
        'venc_garantia' => !empty($_POST['venc_garantia']) ? $_POST['venc_garantia'] : null,
        'estadoEquipo' => $_POST['estadoEquipo'] ?? 1,
        'Nivel' => $_POST['nivel'],
        'activo' => isset($_POST['activo']) ? strtoupper(trim($_POST['activo'])) : '',
    ];

    // Campos específicos según tipo
    if ($tipo == 'PC') {
        $data['procesador'] = strtoupper($_POST['procesador'] ?? '');
        $data['ram'] = $_POST['ram'] ?? '';
        $data['hdd'] = $_POST['hdd'] ?? '';
        $data['cdrom'] = $_POST['cdrom'] ?? 'NO';
        $data['so'] = $_POST['so'] ?? '';
        $data['l_so'] = $_POST['l_so'] ?? '';
        $data['office'] = $_POST['office'] ?? '';
        $data['l_office'] = $_POST['l_office'] ?? '';
        $data['sistemasIsss'] = $_POST['sistemasiss'] ?? '';
        $data['antivirus'] = $_POST['antivirus'] ?? '';
        $data['nombreEquipo'] = $_POST['nombreEquipo'] ?? '';
        $data['ip'] = $_POST['ip'] ?? '';
        $data['dominio'] = $_POST['dominio'] ?? '';
        $data['msus'] = $_POST['msus'] ?? '';
        $data['wsus'] = $_POST['wsus'] ?? '';
        $data['otrosSistemas'] = $_POST['otrossistemas'] ?? '';
        $data['user_dom'] = strtoupper($_POST['user_dom'] ?? '');
        $data['id_partes'] = !empty($_POST['id_partes']) ? $_POST['id_partes'] : null;
        $data['update_usr'] = $user_id;
    } elseif ($tipo == 'IMPRESORA') {
        $data['user_dom'] = strtoupper($_POST['user_dom'] ?? '');
        $data['update_usr'] = $user_id;
    } elseif ($tipo == 'UPS') {
        $data['capacidadSalida'] = strtoupper($_POST['capacidadSalida'] ?? '');
        $data['numTomas'] = strtoupper($_POST['numTomas'] ?? '');
        $data['user_dom'] = strtoupper($_POST['user_dom'] ?? '');
        $data['update_usr'] = $user_id;
    } elseif ($tipo == 'OTROS') {
        $data['updateuser'] = $user_id;
        $data['updatedate'] = $hoy;
    }

    try {
        $db->update($tabla, $data, 'inventario = ?', [$id]);
        $success = 'Equipo actualizado exitosamente';
        // Recargar datos
        $equipo = $db->fetchOne("SELECT * FROM $tabla WHERE inventario = ?", [$id]);
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
    <title>Editar <?= $titulo_tipo ?> - SIR</title>
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
        <div class="alert alert-info">
            <i class="bi bi-pencil-square"></i> 
            Editando <strong><?= $titulo_tipo ?></strong> - Inventario: <strong><?= htmlspecialchars($equipo['inventario']) ?></strong>
        </div>

        <h3><i class="bi bi-pencil-square"></i> Editar <?= $titulo_tipo ?></h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="tipo_equipo" value="<?= $titulo_tipo ?>">
                    
                    <div class="row">
                        <!-- ========================================== -->
                        <!-- CAMPOS DE IDENTIFICACIÓN                  -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Número de Inventario</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($equipo['inventario']) ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Número de Activo</label>
                                <input type="text" class="form-control" name="activo" value="<?= htmlspecialchars($equipo['activo'] ?? '') ?>" placeholder="Opcional">
                            </div>
                        </div>

                        <!-- ========================================== -->
                        <!-- CAMPOS DE UBICACIÓN Y CONTACTO            -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Centro de Costo *</label>
                                <select class="form-select" name="cc" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($ccs as $cc): ?>
                                        <option value="<?= htmlspecialchars($cc['cc']) ?>" <?= $equipo['cc'] == $cc['cc'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cc['cc']) ?> - <?= htmlspecialchars($descripciones_cc[$cc['cc']] ?? 'SIN DESCRIPCIÓN') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Responsable *</label>
                                <input type="text" class="form-control uppercase" name="responsable" value="<?= htmlspecialchars($equipo['responsable']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ubicación *</label>
                                <input type="text" class="form-control uppercase" name="ubicacion" value="<?= htmlspecialchars($equipo['ubicacion']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Teléfono *</label>
                                <input type="text" class="form-control" name="telefono" value="<?= htmlspecialchars($equipo['telefono']) ?>" required>
                            </div>
                        </div>

                        <!-- ========================================== -->
                        <!-- CAMPOS DE EQUIPO                          -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Marca *</label>
                                <input type="text" class="form-control" name="marca" value="<?= htmlspecialchars($equipo['marca']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Modelo *</label>
                                <input type="text" class="form-control uppercase" name="modelo" value="<?= htmlspecialchars($equipo['modelo']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Serie *</label>
                                <input type="text" class="form-control uppercase" name="serie" value="<?= htmlspecialchars($equipo['serie']) ?>" required>
                            </div>
                        </div>

                        <!-- ========================================== -->
                        <!-- CAMPOS ESPECÍFICOS DE PC                  -->
                        <!-- ========================================== -->
                        <?php if ($tipo == 'PC'): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Procesador</label>
                                <input type="text" class="form-control uppercase" name="procesador" value="<?= htmlspecialchars($equipo['procesador'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">RAM</label>
                                <input type="text" class="form-control" name="ram" value="<?= htmlspecialchars($equipo['ram'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Disco Duro</label>
                                <input type="text" class="form-control" name="hdd" value="<?= htmlspecialchars($equipo['hdd'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">CD / DVD</label>
                                <select class="form-select" name="cdrom">
                                    <option value="NO" <?= ($equipo['cdrom'] ?? 'NO') == 'NO' ? 'selected' : '' ?>>NO</option>
                                    <option value="SI" <?= ($equipo['cdrom'] ?? 'NO') == 'SI' ? 'selected' : '' ?>>SI</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sistema Operativo</label>
                                <input type="text" class="form-control" name="so" value="<?= htmlspecialchars($equipo['so'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Licencia SO</label>
                                <input type="text" class="form-control" name="l_so" value="<?= htmlspecialchars($equipo['l_so'] ?? '') ?>" placeholder="Número de licencia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Office</label>
                                <input type="text" class="form-control" name="office" value="<?= htmlspecialchars($equipo['office'] ?? '') ?>" placeholder="Versión de Office">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Licencia Office</label>
                                <input type="text" class="form-control" name="l_office" value="<?= htmlspecialchars($equipo['l_office'] ?? '') ?>" placeholder="Número de licencia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sistemas Institucionales</label>
                                <input type="text" class="form-control" name="sistemasiss" value="<?= htmlspecialchars($equipo['sistemasIsss'] ?? '') ?>" placeholder="Ej: SAFISSS, WEB IMPACTO">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Antivirus</label>
                                <input type="text" class="form-control" name="antivirus" value="<?= htmlspecialchars($equipo['antivirus'] ?? '') ?>" placeholder="Antivirus instalado">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Equipo</label>
                                <input type="text" class="form-control" name="nombreEquipo" value="<?= htmlspecialchars($equipo['nombreEquipo'] ?? '') ?>" placeholder="Nombre en red">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">IP del Equipo</label>
                                <input type="text" class="form-control" name="ip" value="<?= htmlspecialchars($equipo['ip'] ?? '') ?>" placeholder="Dirección IP">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Dominio</label>
                                <input type="text" class="form-control" name="dominio" value="<?= htmlspecialchars($equipo['dominio'] ?? 'isss.gob.sv') ?>" placeholder="Ej: isss.gob.sv">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usuario Dominio</label>
                                <input type="text" class="form-control uppercase" name="user_dom" value="<?= htmlspecialchars($equipo['user_dom'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">MSUS</label>
                                <input type="text" class="form-control" name="msus" value="<?= htmlspecialchars($equipo['msus'] ?? '') ?>" placeholder="MSUS">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">WSUS</label>
                                <input type="text" class="form-control" name="wsus" value="<?= htmlspecialchars($equipo['wsus'] ?? 'OK') ?>" placeholder="WSUS">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Otros Sistemas</label>
                                <input type="text" class="form-control" name="otrossistemas" value="<?= htmlspecialchars($equipo['otrosSistemas'] ?? '') ?>" placeholder="Otros sistemas instalados">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- CAMPOS ESPECÍFICOS DE UPS                 -->
                        <!-- ========================================== -->
                        <?php if ($tipo == 'UPS'): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Capacidad de Salida</label>
                                <input type="text" class="form-control uppercase" name="capacidadSalida" value="<?= htmlspecialchars($equipo['capacidadSalida'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Número de Tomas</label>
                                <input type="text" class="form-control uppercase" name="numTomas" value="<?= htmlspecialchars($equipo['numTomas'] ?? '') ?>">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- CAMPOS ESPECÍFICOS DE IMPRESORA           -->
                        <!-- ========================================== -->
                        <?php if ($tipo == 'IMPRESORA'): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usuario Dominio</label>
                                <input type="text" class="form-control uppercase" name="user_dom" value="<?= htmlspecialchars($equipo['user_dom'] ?? '') ?>">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- CAMPOS DE FECHAS Y ESTADO                 -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fecha de Compra</label>
                                <input type="date" class="form-control" name="f_compra" value="<?= htmlspecialchars($equipo['f_compra'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Vencimiento Garantía</label>
                                <input type="date" class="form-control" name="venc_garantia" value="<?= htmlspecialchars($equipo['venc_garantia'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Estado *</label>
                                <select class="form-select" name="estadoEquipo" required>
                                    <option value="1" <?= ($equipo['estadoEquipo'] ?? 0) == 1 ? 'selected' : '' ?>>Activo</option>
                                    <option value="0" <?= ($equipo['estadoEquipo'] ?? 0) == 0 ? 'selected' : '' ?>>Inactivo</option>
                                    <option value="2" <?= ($equipo['estadoEquipo'] ?? 0) == 2 ? 'selected' : '' ?>>En Mantenimiento</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nivel *</label>
                                <select class="form-select" name="nivel" required>
                                    <option value="">Seleccionar Nivel...</option>
                                    <option value="1" <?= ($equipo['Nivel'] ?? '') == '1' ? 'selected' : '' ?>>Nivel 1</option>
                                    <option value="2" <?= ($equipo['Nivel'] ?? '') == '2' ? 'selected' : '' ?>>Nivel 2</option>
                                    <option value="3" <?= ($equipo['Nivel'] ?? '') == '3' ? 'selected' : '' ?>>Nivel 3</option>
                                    <option value="4" <?= ($equipo['Nivel'] ?? '') == '4' ? 'selected' : '' ?>>Nivel 4</option>
                                    <option value="5" <?= ($equipo['Nivel'] ?? '') == '5' ? 'selected' : '' ?>>Nivel 5</option>
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
    <script>
        document.querySelectorAll('.uppercase').forEach(el => {
            el.addEventListener('blur', function() {
                this.value = this.value.toUpperCase();
            });
        });
    </script>
</body>
</html>