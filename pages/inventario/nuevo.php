<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Recibir parámetros
$tipo = $_GET['tipo'] ?? 'pc';
$numero = $_GET['numero'] ?? '';
$tipo_numero = $_GET['tipo_numero'] ?? 'inventario';

// Mapear tipos a tablas y nombres
$mapa_tipo_tabla = [
    'pc' => 't_inventpc',
    'impresora' => 't_impresores',
    'ups' => 't_ups',
    'monitor' => 't_otros',      // Periféricos van a t_otros
    'teclado' => 't_otros',
    'mouse' => 't_otros',
    'personalizado' => 't_otros',
    'otros' => 't_otros'
];

$mapa_tipo_nombre = [
    'pc' => 'PC',
    'impresora' => 'Impresora',
    'ups' => 'UPS',
    'monitor' => 'Monitor',
    'teclado' => 'Teclado',
    'mouse' => 'Mouse',
    'personalizado' => 'Personalizado',
    'otros' => 'Otros'
];

$tabla = $mapa_tipo_tabla[$tipo] ?? 't_inventpc';
$titulo_tipo = $mapa_tipo_nombre[$tipo] ?? 'Equipo';

// Obtener centros de costo
$ccs = $db->fetchAll("SELECT * FROM t_cc ORDER BY cc");

// Descripciones de centros de costo (solo para vista)
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
// PROCESAR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = Session::get('user_id');
    $hoy = date('Y-m-d');

    $data = [
        'inventario' => $_POST['inventario'],
        'activo' => isset($_POST['activo']) ? strtoupper(trim($_POST['activo'])) : '',
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
        'insertuser' => $user_id,
        'insertdate' => $hoy,
        'updateuser' => $user_id,
        'updatedate' => $hoy
    ];

    // Campos específicos por tipo
    if ($tipo == 'pc') {
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
        $data['update_usr'] = $user_id;
    } elseif ($tipo == 'ups') {
        $data['capacidadSalida'] = strtoupper($_POST['capacidadSalida'] ?? '');
        $data['numTomas'] = strtoupper($_POST['numTomas'] ?? '');
        $data['user_dom'] = strtoupper($_POST['user_dom'] ?? '');
        $data['update_usr'] = $user_id;
    } elseif ($tipo == 'impresora') {
        $data['user_dom'] = strtoupper($_POST['user_dom'] ?? '');
        $data['update_usr'] = $user_id;
    }
    // Para periféricos (monitor, teclado, mouse, otros, personalizado)
    // solo se usan los campos base, no hay campos adicionales

    try {
        $db->insert($tabla, $data);
        $success = 'Equipo registrado exitosamente';
    } catch (Exception $e) {
        $error = 'Error al registrar: ' . $e->getMessage();
    }
}

$mostrar_inventario = $tipo_numero === 'inventario';
$mostrar_activo = $tipo_numero === 'activo';
$campo_principal = $mostrar_inventario ? 'inventario' : 'activo';
$campo_complementario = $mostrar_inventario ? 'activo' : 'inventario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo <?= $titulo_tipo ?> - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php"><i class="bi bi-boxes"></i> SIR</a>
            <a class="btn btn-outline-light" href="buscar.php"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            Registrando nuevo <strong><?= $titulo_tipo ?></strong>
            <?php if ($numero): ?>
                con número de <strong><?= ucfirst($campo_principal) ?>: <?= htmlspecialchars($numero) ?></strong>
            <?php endif; ?>
        </div>

        <h3><i class="bi bi-plus-circle"></i> Nuevo <?= $titulo_tipo ?></h3>
        
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
                    <input type="hidden" name="tabla" value="<?= $tabla ?>">
                    
                    <div class="row">
                        <!-- ========================================== -->
                        <!-- CAMPO PRINCIPAL (OBLIGATORIO)            -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    Número de <?= ucfirst($campo_principal) ?> *
                                </label>
                                <input type="text" class="form-control" 
                                       name="<?= $campo_principal ?>" 
                                       value="<?= htmlspecialchars($numero) ?>" 
                                       required readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    Número de <?= ucfirst($campo_complementario) ?> 
                                    <span class="text-muted">(opcional)</span>
                                </label>
                                <input type="text" class="form-control" 
                                       name="<?= $campo_complementario ?>" 
                                       placeholder="Ingrese número de <?= $campo_complementario ?>"
                                       value="<?= htmlspecialchars($_POST[$campo_complementario] ?? '') ?>">
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
                                        <option value="<?= htmlspecialchars($cc['cc']) ?>">
                                            <?= htmlspecialchars($cc['cc']) ?> - <?= htmlspecialchars($descripciones_cc[$cc['cc']] ?? 'SIN DESCRIPCIÓN') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Responsable *</label>
                                <input type="text" class="form-control uppercase" name="responsable" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ubicación *</label>
                                <input type="text" class="form-control uppercase" name="ubicacion" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Teléfono *</label>
                                <input type="text" class="form-control" name="telefono" required>
                            </div>
                        </div>

                        <!-- ========================================== -->
                        <!-- CAMPOS DE EQUIPO                          -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Marca *</label>
                                <input type="text" class="form-control" name="marca" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Modelo *</label>
                                <input type="text" class="form-control uppercase" name="modelo" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Serie *</label>
                                <input type="text" class="form-control uppercase" name="serie" required>
                            </div>
                        </div>

                        <!-- ========================================== -->
                        <!-- CAMPOS ESPECÍFICOS DE PC                  -->
                        <!-- ========================================== -->
                        <?php if ($tipo == 'pc'): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Procesador</label>
                                <input type="text" class="form-control uppercase" name="procesador">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">RAM</label>
                                <input type="text" class="form-control" name="ram">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Disco Duro</label>
                                <input type="text" class="form-control" name="hdd">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sistema Operativo</label>
                                <input type="text" class="form-control" name="so">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Licencia SO</label>
                                <input type="text" class="form-control" name="l_so" placeholder="Número de licencia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Office</label>
                                <input type="text" class="form-control" name="office" placeholder="Versión de Office">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Licencia Office</label>
                                <input type="text" class="form-control" name="l_office" placeholder="Número de licencia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sistemas Institucionales</label>
                                <input type="text" class="form-control" name="sistemasiss" placeholder="Ej: SAFISSS, WEB IMPACTO">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Antivirus</label>
                                <input type="text" class="form-control" name="antivirus" placeholder="Antivirus instalado">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Equipo</label>
                                <input type="text" class="form-control" name="nombreEquipo" placeholder="Nombre en red">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">IP del Equipo</label>
                                <input type="text" class="form-control" name="ip" placeholder="Dirección IP">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Dominio</label>
                                <input type="text" class="form-control" name="dominio" placeholder="Ej: isss.gob.sv">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usuario Dominio</label>
                                <input type="text" class="form-control uppercase" name="user_dom">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">MSUS</label>
                                <input type="text" class="form-control" name="msus" placeholder="MSUS">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">WSUS</label>
                                <input type="text" class="form-control" name="wsus" placeholder="WSUS">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Otros Sistemas</label>
                                <input type="text" class="form-control" name="otrossistemas" placeholder="Otros sistemas instalados">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">CD / DVD</label>
                                <select class="form-select" name="cdrom">
                                    <option value="NO">NO</option>
                                    <option value="SI">SI</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- CAMPOS ESPECÍFICOS DE UPS                 -->
                        <!-- ========================================== -->
                        <?php if ($tipo == 'ups'): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Capacidad de Salida</label>
                                <input type="text" class="form-control uppercase" name="capacidadSalida">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Número de Tomas</label>
                                <input type="text" class="form-control uppercase" name="numTomas">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- CAMPOS ESPECÍFICOS DE IMPRESORA           -->
                        <!-- ========================================== -->
                        <?php if ($tipo == 'impresora'): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usuario Dominio</label>
                                <input type="text" class="form-control uppercase" name="user_dom">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- CAMPOS DE FECHAS Y ESTADO                 -->
                        <!-- ========================================== -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fecha de Compra</label>
                                <input type="date" class="form-control" name="f_compra">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Vencimiento Garantía</label>
                                <input type="date" class="form-control" name="venc_garantia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Estado *</label>
                                <select class="form-select" name="estadoEquipo" required>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                    <option value="2">En Mantenimiento</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nivel *</label>
                                <select class="form-select" name="nivel" required>
                                    <option value="">Seleccionar Nivel...</option>
                                    <option value="1">Nivel 1</option>
                                    <option value="2">Nivel 2</option>
                                    <option value="3">Nivel 3</option>
                                    <option value="4">Nivel 4</option>
                                    <option value="5">Nivel 5</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                    <a href="buscar.php" class="btn btn-secondary">Cancelar</a>
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