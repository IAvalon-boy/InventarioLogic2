<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

$numero = $_POST['numero'] ?? '';
$tipo_numero = $_POST['tipo_numero'] ?? 'inventario';
$tipo_equipo = $_POST['tipo_equipo'] ?? '';
$error = '';
$equipo_existente = null;

// Tipos principales
$tipos_principales = [
    'pc' => ['nombre' => 'PC', 'icono' => 'bi-laptop', 'color' => 'info'],
    'impresora' => ['nombre' => 'Impresora', 'icono' => 'bi-printer', 'color' => 'warning'],
    'ups' => ['nombre' => 'UPS', 'icono' => 'bi-battery-charging', 'color' => 'secondary']
];

// Tipos secundarios (se despliegan dentro de "Otros")
$tipos_secundarios = [
    'monitor' => ['nombre' => 'Monitor', 'icono' => 'bi-display', 'color' => 'primary'],
    'teclado' => ['nombre' => 'Teclado', 'icono' => 'bi-keyboard', 'color' => 'dark'],
    'mouse' => ['nombre' => 'Mouse', 'icono' => 'bi-mouse', 'color' => 'dark']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($numero) && !empty($tipo_equipo)) {
    $equipo = $db->fetchOne("SELECT * FROM t_inventpc WHERE inventario = ? OR activo = ?", [$numero, $numero]);
    if (!$equipo) {
        $equipo = $db->fetchOne("SELECT * FROM t_impresores WHERE inventario = ? OR activo = ?", [$numero, $numero]);
    }
    if (!$equipo) {
        $equipo = $db->fetchOne("SELECT * FROM t_ups WHERE inventario = ? OR activo = ?", [$numero, $numero]);
    }
    if (!$equipo) {
        $equipo = $db->fetchOne("SELECT * FROM t_otros WHERE inventario = ? OR activo = ?", [$numero, $numero]);
    }
    
    if ($equipo) {
        $equipo_existente = $equipo;
        $error = "⚠️ El número <strong>$numero</strong> ya está registrado como <strong>" . 
                 ($equipo['tipo'] ?? 'Equipo') . "</strong>.";
    } else {
        $mapa_tipo = [
            'pc' => 'nuevo.php?tipo=pc&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'impresora' => 'nuevo.php?tipo=impresora&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'ups' => 'nuevo.php?tipo=ups&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'monitor' => 'nuevo.php?tipo=monitor&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'teclado' => 'nuevo.php?tipo=teclado&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'mouse' => 'nuevo.php?tipo=mouse&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'personalizado' => 'nuevo.php?tipo=personalizado&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero),
            'otros' => 'nuevo.php?tipo=otros&numero=' . urlencode($numero) . '&tipo_numero=' . urlencode($tipo_numero)
        ];
        if (isset($mapa_tipo[$tipo_equipo])) {
            header('Location: ' . $mapa_tipo[$tipo_equipo]);
            exit;
        }
    }
}

$totalPC = $db->fetchOne("SELECT COUNT(*) as total FROM t_inventpc")['total'] ?? 0;
$totalImp = $db->fetchOne("SELECT COUNT(*) as total FROM t_impresores")['total'] ?? 0;
$totalUps = $db->fetchOne("SELECT COUNT(*) as total FROM t_ups")['total'] ?? 0;
$totalOtros = $db->fetchOne("SELECT COUNT(*) as total FROM t_otros")['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Equipo - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1a237e, #0d1757);
            padding: 8px 16px;
        }
        .navbar-custom .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff;
        }
        .navbar-custom .navbar-brand i {
            margin-right: 8px;
        }
        .navbar-custom .btn-outline-light {
            border-color: rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            padding: 4px 14px;
            border-radius: 30px;
        }
        .navbar-custom .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-custom .card-header {
            background: linear-gradient(135deg, #1a237e, #0d1757);
            color: #fff;
            padding: 12px 20px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        .card-custom .card-header i {
            margin-right: 8px;
        }
        .card-custom .card-body {
            background: #fff;
            padding: 24px 28px !important;
        }
        .form-label-custom {
            color: #555;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .form-control-custom {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control-custom:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .form-select-custom {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            background-color: #fff;
            cursor: pointer;
        }
        .form-select-custom:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #1a237e, #0d1757);
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .btn-primary-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);
            color: #fff;
        }
        .btn-success-custom {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 8px;
            padding: 12px 24px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        .btn-success-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            color: #fff;
        }
        .btn-success-custom:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-outline-custom {
            background: transparent;
            border: 1px solid #ddd;
            color: #777;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.85rem;
            width: 100%;
        }
        .btn-outline-custom:hover {
            background: #f5f5f5;
            border-color: #bbb;
            color: #333;
        }
        .btn-outline-danger-custom {
            background: transparent;
            border: 1px solid #e0c0c0;
            color: #c62828;
            border-radius: 6px;
            padding: 8px 16px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.8rem;
            width: 100%;
        }
        .btn-outline-danger-custom:hover {
            background: #ffebee;
            border-color: #c62828;
            color: #b71c1c;
        }
        .badge-custom {
            background: #e8eaf6;
            color: #1a237e;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-danger {
            background: #ffebee;
            color: #c62828;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-warning {
            background: #fff8e1;
            color: #f57f17;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-secondary {
            background: #f5f5f5;
            color: #888;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-custom-dark {
            background: #e0e0e0;
            color: #333;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .tipo-btn {
            transition: all 0.3s ease;
            border-radius: 8px !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 10px 8px;
            font-size: 0.8rem;
            border-width: 2px;
            height: 100%;
        }
        .tipo-btn i {
            font-size: 1.5rem;
            margin-bottom: 4px;
            display: block;
        }
        .tipo-btn.active {
            border-color: #1a237e !important;
            background: #e8eaf6 !important;
            color: #1a237e !important;
            transform: scale(0.97);
        }
        .tipo-btn.btn-outline-info {
            color: #0d47a1;
            border-color: #90caf9;
        }
        .tipo-btn.btn-outline-info:hover {
            background: #e3f2fd;
            border-color: #0d47a1;
            color: #0d47a1;
        }
        .tipo-btn.btn-outline-warning {
            color: #e65100;
            border-color: #ffcc80;
        }
        .tipo-btn.btn-outline-warning:hover {
            background: #fff3e0;
            border-color: #e65100;
            color: #e65100;
        }
        .tipo-btn.btn-outline-secondary {
            color: #555;
            border-color: #ccc;
        }
        .tipo-btn.btn-outline-secondary:hover {
            background: #f5f5f5;
            border-color: #888;
            color: #333;
        }
        .tipo-btn.btn-outline-primary {
            color: #0d47a1;
            border-color: #90caf9;
        }
        .tipo-btn.btn-outline-primary:hover {
            background: #e3f2fd;
            border-color: #0d47a1;
            color: #0d47a1;
        }
        .tipo-btn.btn-outline-dark {
            color: #555;
            border-color: #ccc;
        }
        .tipo-btn.btn-outline-dark:hover {
            background: #f5f5f5;
            border-color: #888;
            color: #333;
        }
        .tipo-btn.btn-outline-danger {
            color: #b71c1c;
            border-color: #ef9a9a;
        }
        .tipo-btn.btn-outline-danger:hover {
            background: #ffebee;
            border-color: #b71c1c;
            color: #b71c1c;
        }
        .tipo-btn.btn-outline-success {
            color: #1b5e20;
            border-color: #a5d6a7;
        }
        .tipo-btn.btn-outline-success:hover {
            background: #e8f5e9;
            border-color: #1b5e20;
            color: #1b5e20;
        }
        .input-group-custom .input-group-text {
            background: #f5f5f5;
            border: 1px solid #ddd;
            color: #1a237e;
            font-weight: 600;
            font-size: 0.8rem;
            border-radius: 8px 0 0 8px;
            padding: 8px 14px;
        }
        .input-group-custom .form-control-custom {
            border-radius: 0 8px 8px 0;
        }
        .alert-custom-warning {
            background: #fff8e1;
            border: 1px solid #ffecb3;
            color: #e65100;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .alert-custom-warning a {
            color: #1a237e;
        }
        .alert-custom-warning a:hover {
            color: #0d1757;
        }
        .text-muted-custom {
            color: #aaa;
            font-size: 0.65rem;
        }
        .text-muted-custom strong {
            color: #888;
        }
        .footer-custom {
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 16px;
            text-align: center;
        }
        .footer-custom span {
            color: #ccc;
            font-size: 0.5rem;
            letter-spacing: 2px;
        }
        .separator {
            border-top: 1px solid #eee;
            margin: 12px 0;
        }
        .row.g-2 {
            --bs-gutter-x: 0.5rem;
            --bs-gutter-y: 0.5rem;
        }
        .mt-1 { margin-top: 4px !important; }
        .mt-2 { margin-top: 8px !important; }
        .mt-3 { margin-top: 12px !important; }
        .mb-1 { margin-bottom: 4px !important; }
        .mb-2 { margin-bottom: 8px !important; }
        .mb-3 { margin-bottom: 12px !important; }
        .btn-sm-custom {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 14px;
            border: 1px solid #eee;
        }
        .info-box .info-label {
            color: #999;
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-box .info-value {
            color: #333;
            font-size: 0.7rem;
            font-family: monospace;
        }
        .tipo-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        @media (max-width: 768px) {
            .tipo-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .btn-agregar-tipo {
            border: 1px dashed #ccc;
            color: #999;
            background: transparent;
            transition: all 0.3s ease;
            border-radius: 8px;
            padding: 12px 8px;
            width: 100%;
            font-size: 0.75rem;
            font-weight: 600;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .btn-agregar-tipo:hover {
            border-color: #1a237e;
            color: #1a237e;
            background: rgba(26, 35, 126, 0.05);
        }
        .subtipos-container {
            display: none;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 8px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .subtipos-container.active {
            display: grid;
        }
        .subtipos-container .tipo-btn {
            font-size: 0.7rem;
            padding: 8px 6px;
        }
        .subtipos-container .tipo-btn i {
            font-size: 1.2rem;
        }
        @media (max-width: 576px) {
            .subtipos-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

    <!-- ========================================== -->
    <!-- NAVBAR INSTITUCIONAL                       -->
    <!-- ========================================== -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">
                <i class="bi bi-boxes"></i> SIR
            </a>
            <a class="btn btn-outline-light" href="index.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </nav>

    <!-- ========================================== -->
    <!-- CONTENIDO PRINCIPAL                        -->
    <!-- ========================================== -->
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- TÍTULO -->
                <div class="text-center mb-3">
                    <h2 style="color: #1a237e; font-weight: 700; font-size: 1.5rem;">
                        <i class="bi bi-plus-circle"></i> Registrar Equipo
                    </h2>
                    <p style="color: #999; font-size: 0.75rem; letter-spacing: 1px; text-transform: uppercase;">
                        Ingresa número y selecciona tipo de equipo
                    </p>
                </div>

                <!-- ========================================== -->
                <!-- TARJETA PRINCIPAL                          -->
                <!-- ========================================== -->
                <div class="card card-custom">
                    <div class="card-header">
                        <i class="bi bi-search"></i> Buscador de Equipos
                        <span class="badge-custom ms-2">Nuevo Registro</span>
                    </div>
                    <div class="card-body">

                        <!-- ========================================== -->
                        <!-- MENSAJE DE ERROR                          -->
                        <!-- ========================================== -->
                        <?php if ($error): ?>
                            <div class="alert-custom-warning mb-3">
                                <?= $error ?>
                                <?php if ($equipo_existente): ?>
                                    <br>
                                    <a href="detalle.php?id=<?= urlencode($equipo_existente['inventario']) ?>" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-eye"></i> Ver equipo
                                    </a>
                                    <a href="editar.php?id=<?= urlencode($equipo_existente['inventario']) ?>" class="btn btn-sm btn-outline-warning mt-2">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- ========================================== -->
                        <!-- FORMULARIO                               -->
                        <!-- ========================================== -->
                        <form method="POST" id="registroForm">

                            <!-- Número y Tipo de Número -->
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <div class="form-label-custom">
                                        <i class="bi bi-hash"></i> Número *
                                    </div>
                                    <input type="text" class="form-control form-control-custom" 
                                           name="numero" id="numero" 
                                           value="<?= htmlspecialchars($numero) ?>"
                                           placeholder="Ingrese número" 
                                           required autofocus>
                                    <div class="text-muted-custom mt-1">
                                        Ej: 353105392 o ACT-001
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-label-custom">
                                        <i class="bi bi-tag"></i> Es *
                                    </div>
                                    <select class="form-select form-select-custom" name="tipo_numero" id="tipoNumero">
                                        <option value="inventario" <?= $tipo_numero=='inventario'?'selected':'' ?>>Inventario</option>
                                        <option value="activo" <?= $tipo_numero=='activo'?'selected':'' ?>>Activo</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-label-custom">&nbsp;</div>
                                    <button type="button" class="btn btn-outline-custom" id="btnAgregarOtro">
                                        <i class="bi bi-plus-circle"></i> Agregar
                                    </button>
                                </div>
                            </div>

                            <!-- Campos Adicionales (dinámico) -->
                            <div id="camposAdicionales" class="mt-2"></div>

                            <!-- Tipo de Equipo -->
                            <div class="mt-3">
                                <div class="form-label-custom">
                                    <i class="bi bi-tag"></i> Tipo de Equipo *
                                </div>

                                <!-- Tipos principales (PC, Impresora, UPS) -->
                                <div class="tipo-grid">
                                    <?php foreach ($tipos_principales as $key => $tipo): ?>
                                    <button type="button" data-tipo="<?= $key ?>" 
                                            class="btn btn-outline-<?= $tipo['color'] ?> w-100 py-3 tipo-btn">
                                        <i class="bi <?= $tipo['icono'] ?>"></i>
                                        <span style="font-size: 0.75rem;"><?= $tipo['nombre'] ?></span>
                                        <span class="badge-custom d-block mt-1" style="font-size: 0.55rem;">
                                            <?= $key == 'pc' ? $totalPC : ($key == 'impresora' ? $totalImp : $totalUps) ?>
                                        </span>
                                    </button>
                                    <?php endforeach; ?>

                                    <!-- Botón OTROS (desplegable) -->
                                    <button type="button" class="btn btn-outline-danger w-100 py-3 tipo-btn" id="btnOtros">
                                        <i class="bi bi-box"></i>
                                        <span style="font-size: 0.75rem;">Otros</span>
                                        <span class="badge-custom-danger d-block mt-1" style="font-size: 0.55rem;"><?= $totalOtros ?></span>
                                        <span style="font-size: 0.55rem; color: #999;"><i class="bi bi-chevron-down" id="iconOtros"></i></span>
                                    </button>
                                </div>

                                <!-- Subtipos (Monitor, Teclado, Mouse) -->
                                <div class="subtipos-container" id="subtiposContainer">
                                    <?php foreach ($tipos_secundarios as $key => $tipo): ?>
                                    <button type="button" data-tipo="<?= $key ?>" 
                                            class="btn btn-outline-<?= $tipo['color'] ?> w-100 py-2 tipo-btn">
                                        <i class="bi <?= $tipo['icono'] ?>"></i>
                                        <span style="font-size: 0.7rem;"><?= $tipo['nombre'] ?></span>
                                        <span class="badge-custom d-block mt-1" style="font-size: 0.5rem;">0</span>
                                    </button>
                                    <?php endforeach; ?>
                                    
                                    <!-- Botón + Agregar otro tipo personalizado -->
                                    <button type="button" class="btn-agregar-tipo" id="btnAgregarTipo">
                                        <i class="bi bi-plus-circle" style="font-size: 1.2rem;"></i>
                                        <span style="font-size: 0.65rem;">Agregar otro</span>
                                    </button>
                                </div>

                                <!-- Campos para nuevo tipo personalizado (solo aparece al hacer clic en +) -->
                                <div id="camposTipoPersonalizado" class="mt-2" style="display: none;">
                                    <div class="row g-2">
                                        <div class="col-md-8">
                                            <label class="form-label-custom" style="font-size: 0.65rem;">Nombre del nuevo tipo</label>
                                            <input type="text" class="form-control form-control-custom" id="nuevoTipoInput" placeholder="Ej: Escáner, Proyector, Tablet">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary-custom" id="btnConfirmarNuevoTipo" style="padding: 8px; font-size: 0.75rem;">
                                                <i class="bi bi-check-lg"></i> Agregar
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary ms-1" id="btnCancelarNuevoTipo" style="padding: 8px; font-size: 0.75rem;">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="tipoSeleccionado" class="text-center mt-2"></div>
                            </div>

                            <!-- Botón Continuar -->
                            <button type="submit" class="btn btn-success-custom mt-3" id="btnContinuar" disabled>
                                <i class="bi bi-arrow-right-circle"></i> CONTINUAR
                            </button>

                        </form>

                        <!-- ========================================== -->
                        <!-- ACCESO A CARGA RÁPIDA                     -->
                        <!-- ========================================== -->
                        <div class="separator"></div>
                        <a href="carga_rapida.php" class="btn btn-outline-custom w-100" style="color: #888; border-color: #ddd;">
                            <i class="bi bi-lightning-charge" style="color: #f57f17;"></i> Carga Rápida (CSV)
                        </a>

                    </div>
                </div>

                <!-- ========================================== -->
                <!-- FOOTER                                    -->
                <!-- ========================================== -->
                <div class="footer-custom">
                    <span><i class="bi bi-cpu"></i> SIR v3.0</span>
                </div>

            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- SCRIPTS                                    -->
    <!-- ========================================== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================
        // VARIABLES
        // ============================================
        let tipoSeleccionado = null;
        let camposAdicionales = [];

        // ============================================
        // DESPLEGAR SUBTIPOS (OTROS)
        // ============================================
        document.getElementById('btnOtros').addEventListener('click', function() {
            const container = document.getElementById('subtiposContainer');
            const icon = document.getElementById('iconOtros');
            
            if (container.classList.contains('active')) {
                container.classList.remove('active');
                icon.className = 'bi bi-chevron-down';
            } else {
                container.classList.add('active');
                icon.className = 'bi bi-chevron-up';
            }
        });

        // ============================================
        // AGREGAR CAMPO COMPLEMENTARIO (Inventario/Activo)
        // ============================================
        document.getElementById('btnAgregarOtro').addEventListener('click', function() {
            const tipoActual = document.getElementById('tipoNumero').value;
            const tipoComplementario = tipoActual === 'inventario' ? 'activo' : 'inventario';
            
            if (camposAdicionales.includes(tipoComplementario)) {
                alert('El campo ' + tipoComplementario + ' ya está agregado.');
                return;
            }

            const container = document.getElementById('camposAdicionales');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2';
            row.id = 'campo-' + tipoComplementario;
            
            const label = tipoComplementario.charAt(0).toUpperCase() + tipoComplementario.slice(1);
            const color = tipoComplementario === 'inventario' ? '#1a237e' : '#e65100';
            
            row.innerHTML = `
                <div class="col-md-8">
                    <div class="input-group input-group-custom">
                        <span class="input-group-text" style="color: ${color};">${label}</span>
                        <input type="text" class="form-control form-control-custom" 
                               name="${tipoComplementario}" 
                               placeholder="Ingrese número de ${tipoComplementario}"
                               style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-outline-danger-custom" onclick="eliminarCampo('${tipoComplementario}')">
                        <i class="bi bi-x-circle"></i> Quitar
                    </button>
                </div>
            `;
            
            container.appendChild(row);
            camposAdicionales.push(tipoComplementario);
        });

        // ============================================
        // ELIMINAR CAMPO COMPLEMENTARIO
        // ============================================
        function eliminarCampo(tipo) {
            const elemento = document.getElementById('campo-' + tipo);
            if (elemento) {
                elemento.remove();
                camposAdicionales = camposAdicionales.filter(t => t !== tipo);
            }
        }

        // ============================================
        // AGREGAR TIPO PERSONALIZADO (solo aparece al hacer clic en + Agregar otro)
        // ============================================
        document.getElementById('btnAgregarTipo').addEventListener('click', function() {
            const container = document.getElementById('camposTipoPersonalizado');
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                document.getElementById('nuevoTipoInput').focus();
            } else {
                container.style.display = 'none';
                document.getElementById('nuevoTipoInput').value = '';
            }
        });

        // Cancelar nuevo tipo
        document.getElementById('btnCancelarNuevoTipo').addEventListener('click', function() {
            document.getElementById('camposTipoPersonalizado').style.display = 'none';
            document.getElementById('nuevoTipoInput').value = '';
        });

        // Confirmar nuevo tipo
        document.getElementById('btnConfirmarNuevoTipo').addEventListener('click', function() {
            const nombre = document.getElementById('nuevoTipoInput').value.trim();
            if (!nombre) {
                alert('Ingrese un nombre para el nuevo tipo');
                return;
            }
            
            const container = document.getElementById('subtiposContainer');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.tipo = 'personalizado';
            btn.className = 'btn btn-outline-success w-100 py-2 tipo-btn';
            btn.innerHTML = `
                <i class="bi bi-plus-circle" style="color: #2e7d32;"></i>
                <span style="font-size: 0.7rem; color: #2e7d32;">${nombre}</span>
                <span class="badge-custom-success d-block mt-1" style="font-size: 0.5rem;">NUEVO</span>
            `;
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tipo-btn').forEach(b => {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                tipoSeleccionado = 'personalizado';
                
                document.getElementById('tipoSeleccionado').innerHTML = 
                    '<span class="badge-custom-success">✓ Seleccionado: <strong>' + nombre + '</strong></span>';
                document.getElementById('btnContinuar').disabled = false;
            });
            
            // Insertar antes del botón de agregar
            const addBtn = container.querySelector('#btnAgregarTipo');
            container.insertBefore(btn, addBtn);
            
            // Ocultar el input y limpiar
            document.getElementById('camposTipoPersonalizado').style.display = 'none';
            document.getElementById('nuevoTipoInput').value = '';
            
            // Expandir el contenedor de subtipos
            container.classList.add('active');
            document.getElementById('iconOtros').className = 'bi bi-chevron-up';
        });

        // Enter para confirmar nuevo tipo
        document.getElementById('nuevoTipoInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btnConfirmarNuevoTipo').click();
            }
        });

        // ============================================
        // SELECCIONAR TIPO DE EQUIPO
        // ============================================
        document.querySelectorAll('.tipo-btn[data-tipo]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tipo-btn').forEach(b => {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                tipoSeleccionado = this.dataset.tipo;
                
                const nombres = {
                    'pc': 'PC',
                    'impresora': 'Impresora',
                    'ups': 'UPS',
                    'monitor': 'Monitor',
                    'teclado': 'Teclado',
                    'mouse': 'Mouse'
                };
                
                const nombreMostrar = nombres[tipoSeleccionado] || tipoSeleccionado;
                document.getElementById('tipoSeleccionado').innerHTML = 
                    '<span class="badge-custom-success">✓ Seleccionado: <strong>' + nombreMostrar + '</strong></span>';
                
                document.getElementById('btnContinuar').disabled = false;
            });
        });

        // ============================================
        // ENVIAR FORMULARIO
        // ============================================
        document.getElementById('registroForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const numero = document.getElementById('numero').value.trim();
            if (!numero) {
                alert('Ingrese un número');
                document.getElementById('numero').focus();
                return;
            }
            
            if (!tipoSeleccionado) {
                alert('Seleccione un tipo de equipo');
                return;
            }
            
            const inputNumero = document.createElement('input');
            inputNumero.type = 'hidden';
            inputNumero.name = 'numero';
            inputNumero.value = numero;
            
            const inputTipoNumero = document.createElement('input');
            inputTipoNumero.type = 'hidden';
            inputTipoNumero.name = 'tipo_numero';
            inputTipoNumero.value = document.getElementById('tipoNumero').value;
            
            const inputTipoEquipo = document.createElement('input');
            inputTipoEquipo.type = 'hidden';
            inputTipoEquipo.name = 'tipo_equipo';
            inputTipoEquipo.value = tipoSeleccionado;
            
            this.appendChild(inputNumero);
            this.appendChild(inputTipoNumero);
            this.appendChild(inputTipoEquipo);
            
            this.submit();
        });

        // ============================================
        // BUSCAR CON ENTER
        // ============================================
        document.getElementById('numero').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!tipoSeleccionado) {
                    alert('Seleccione un tipo de equipo');
                    return;
                }
                document.getElementById('registroForm').submit();
            }
        });
    </script>
</body>
</html>