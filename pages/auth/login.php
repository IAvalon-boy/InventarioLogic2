<?php
require_once '../../includes/session.php';
require_once '../../includes/database.php';

Session::start();

// Si ya está logueado, redirigir al dashboard
if (Session::isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $pass = $_POST['pass'] ?? '';
    
    if (empty($usuario) || empty($pass)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM t_usuario WHERE codUsuario = ?",
            [$usuario]
        );
        
        if ($user && $user['password'] === sha1($pass) && $user['status'] == 1) {
            Session::setUser($user);
            header('Location: ../../index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .login-card h2 {
            text-align: center;
            color: #1a237e;
            margin-bottom: 30px;
        }
        .login-card .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2><i class="bi bi-boxes"></i> SIR</h2>
        <h5 class="text-center text-muted">Sistema de Inventario</h5>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required autofocus>
            </div>
            <div class="mb-3">
                <label for="pass" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="pass" name="pass" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
        
        <div class="footer">
            &copy; <?= date('Y') ?> - Sistema de Control de Inventarios
        </div>
    </div>
</body>
</html>