<?php
require_once 'includes/session.php';
require_once 'includes/database.php';

Session::start();

// Redirigir al login si no está autenticado
if (!Session::isLoggedIn()) {
    header('Location: pages/auth/login.php');
    exit;
}

$user = Session::getUser();
$db = Database::getInstance();

// Contadores
$totalPc = $db->fetchOne("SELECT COUNT(*) as total FROM t_inventpc")['total'] ?? 0;
$totalImp = $db->fetchOne("SELECT COUNT(*) as total FROM t_impresores")['total'] ?? 0;
$totalUps = $db->fetchOne("SELECT COUNT(*) as total FROM t_ups")['total'] ?? 0;
$totalOtros = $db->fetchOne("SELECT COUNT(*) as total FROM t_otros")['total'] ?? 0;
$totalReq = $db->fetchOne("SELECT COUNT(*) as total FROM t_requerimiento WHERE estatus = 'PENDIENTE'")['total'] ?? 0;

// Últimos requerimientos
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
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cyber-style.css">
</head>
<body>

    <!-- ============================================ -->
    <!-- NAVBAR CYBER -->
    <!-- ============================================ -->
    <nav class="navbar navbar-expand-lg cyber-navbar">
        <div class="container-fluid">
            <a class="navbar-brand cyber-brand" href="#">
                <span class="neon-text">Sistema Inventario</span>
                <span class="brand-sub">By Daniel</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link cyber-user">
                            <i class="bi bi-person-circle"></i>
                            <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                            <span class="user-badge">NIVEL <?= htmlspecialchars($user['level']) ?></span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cyber-logout" href="pages/auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ============================================ -->
    <!-- BANNER CYBER CON LOGO INSTITUCIONAL -->
    <!-- ============================================ -->
    <div class="cyber-banner">
        <div class="cyber-banner-content">
            <div class="logo-container">
                <img src="assets/images/fondo.png" alt="Logo Institucional" class="cyber-logo">
            </div>
            <div class="banner-text">
                <h1 class="cyber-title">Sistema de Control de Inventarios</h1>
                <p class="cyber-subtitle">y Registro de Requerimientos</p>
                <div class="cyber-line"></div>
                <p class="cyber-welcome">Bienvenido, <span class="neon-highlight"><?= htmlspecialchars($user['name']) ?></span></p>
            </div>
        </div>
        <div class="cyber-grid-overlay"></div>
        <div class="cyber-scanline"></div>
    </div>

    <!-- ============================================ -->
    <!-- CONTENIDO PRINCIPAL -->
    <!-- ============================================ -->
    <div class="container-fluid cyber-main">

        <!-- ESTADÍSTICAS CYBER -->
        <div class="row cyber-stats">
            <div class="col-md-2 col-6">
                <div class="cyber-stat-card stat-pc">
                    <div class="stat-icon"><i class="bi bi-laptop"></i></div>
                    <div class="stat-number cyber-counter" data-target="<?= $totalPc ?>">0</div>
                    <div class="stat-label">Computadoras</div>
                    <div class="stat-glow"></div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="cyber-stat-card stat-imp">
                    <div class="stat-icon"><i class="bi bi-printer"></i></div>
                    <div class="stat-number cyber-counter" data-target="<?= $totalImp ?>">0</div>
                    <div class="stat-label">Impresoras</div>
                    <div class="stat-glow"></div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="cyber-stat-card stat-ups">
                    <div class="stat-icon"><i class="bi bi-battery-charging"></i></div>
                    <div class="stat-number cyber-counter" data-target="<?= $totalUps ?>">0</div>
                    <div class="stat-label">UPS</div>
                    <div class="stat-glow"></div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="cyber-stat-card stat-otros">
                    <div class="stat-icon"><i class="bi bi-box"></i></div>
                    <div class="stat-number cyber-counter" data-target="<?= $totalOtros ?>">0</div>
                    <div class="stat-label">Otros</div>
                    <div class="stat-glow"></div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="cyber-stat-card stat-req">
                    <div class="stat-icon"><i class="bi bi-clipboard"></i></div>
                    <div class="stat-number cyber-counter" data-target="<?= $totalReq ?>">0</div>
                    <div class="stat-label">Req. Pendientes</div>
                    <div class="stat-glow"></div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="cyber-stat-card stat-total">
                    <div class="stat-icon"><i class="bi bi-database"></i></div>
                    <div class="stat-number cyber-counter" data-target="<?= $totalPc + $totalImp + $totalUps + $totalOtros ?>">0</div>
                    <div class="stat-label">Total Activos</div>
                    <div class="stat-glow"></div>
                </div>
            </div>
        </div>

        <!-- ACCESOS RÁPIDOS Y REQUERIMIENTOS -->
        <div class="row cyber-row">
            <!-- Menú de Acceso Rápido -->
            <div class="col-md-3">
                <div class="cyber-card cyber-menu">
                    <div class="cyber-card-header">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Acceso Rápido
                        <span class="cyber-dot"></span>
                    </div>
                    <div class="cyber-card-body">
                        <a href="pages/inventario/index.php" class="cyber-menu-item">
                            <i class="bi bi-laptop"></i>
                            <span>Inventario</span>
                            <span class="menu-arrow">→</span>
                        </a>
                        <a href="pages/inventario/buscar.php" class="cyber-menu-item">
                            <i class="bi bi-plus-circle"></i>
                            <span>Nuevo Equipo</span>
                            <span class="menu-arrow">→</span>
                        </a>
                        <a href="pages/requerimientos/index.php" class="cyber-menu-item">
                            <i class="bi bi-clipboard"></i>
                            <span>Requerimientos</span>
                            <span class="menu-arrow">→</span>
                        </a>
                        <a href="pages/requerimientos/nuevo.php" class="cyber-menu-item">
                            <i class="bi bi-plus-circle"></i>
                            <span>Nuevo Requerimiento</span>
                            <span class="menu-arrow">→</span>
                        </a>
                        <a href="pages/reportes/index.php" class="cyber-menu-item">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            <span>Reportes</span>
                            <span class="menu-arrow">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Últimos Requerimientos -->
            <div class="col-md-9">
                <div class="cyber-card cyber-requests">
                    <div class="cyber-card-header">
                        <i class="bi bi-clock-history"></i> Últimos Requerimientos
                        <span class="cyber-badge">EN VIVO</span>
                        <span class="cyber-dot"></span>
                    </div>
                    <div class="cyber-card-body">
                        <?php if (!empty($ultimosReq)): ?>
                            <div class="table-responsive">
                                <table class="cyber-table">
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
                                            <td><span class="cyber-id">#<?= htmlspecialchars($req['requerimiento']) ?></span></td>
                                            <td><?= htmlspecialchars($req['inventario']) ?></td>
                                            <td><?= htmlspecialchars($req['activo'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($req['responsable']) ?></td>
                                            <td><?= htmlspecialchars(substr($req['falla'], 0, 35)) . (strlen($req['falla']) > 35 ? '...' : '') ?></td>
                                            <td>
                                                <span class="cyber-status <?= $req['estatus'] == 'FINALIZADO' ? 'status-done' : 'status-pending' ?>">
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
                            <div class="cyber-empty">
                                <i class="bi bi-inbox"></i>
                                <p>No hay requerimientos registrados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER CYBER -->
        <div class="cyber-footer">
            <div class="cyber-footer-content">
                <span class="cyber-footer-text">
                    <i class="bi bi-cpu"></i> SIR v3.0.1
                </span>
                <span class="cyber-footer-text">
                    <i class="bi bi-shield-check"></i> Sistema Seguro
                </span>
                <span class="cyber-footer-text">
                    <i class="bi bi-clock"></i> <?= date('Y-m-d H:i:s') ?>
                </span>
                <span class="cyber-footer-text">
                    <i class="bi bi-person"></i> <?= htmlspecialchars($user['name']) ?>
                </span>
            </div>
            <div class="cyber-footer-line"></div>
            <div class="cyber-footer-copy">
                &copy; <?= date('Y') ?> - Sistema de Control de Inventarios y Registro de Requerimientos
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- SCRIPTS -->
    <!-- ============================================ -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================
        // CONTADOR DE ANIMACIÓN CYBER
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.cyber-counter');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'));
                const duration = 1500;
                const step = Math.max(1, Math.floor(target / 60));
                let current = 0;
                
                const updateCounter = () => {
                    current += step;
                    if (current >= target) {
                        counter.textContent = target;
                        return;
                    }
                    counter.textContent = current;
                    requestAnimationFrame(updateCounter);
                };
                
                // Iniciar animación con retraso escalonado
                setTimeout(() => {
                    updateCounter();
                }, Math.random() * 500);
            });
        });

        // ============================================
        // EFECTO DE SCANLINE (opcional)
        // ============================================
        document.addEventListener('mousemove', function(e) {
            const scanline = document.querySelector('.cyber-scanline');
            if (scanline) {
                const y = (e.clientY / window.innerHeight) * 100;
                scanline.style.top = y + '%';
            }
        });
    </script>
</body>
</html>