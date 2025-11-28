<?php
session_start();

// Incluir configuración de base de datos
require_once 'config/database.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    
    if (!empty($nombre) && !empty($contrasena)) {
        try {
            // Verificar si la conexión PDO está disponible usando la clase Database
            $database = new Database();
            $pdo = $database->getConnection();
            
            if (!$pdo) {
                throw new Exception("Error de conexión a la base de datos");
            }
            
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nombre = ?");
            $stmt->execute([$nombre]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // En un sistema real, usar password_verify()
                // Aquí usamos comparación directa solo para desarrollo
                if ($contrasena === $usuario['password']) {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Contraseña incorrecta';
                }
            } else {
                $error = 'Usuario no encontrado';
            }
        } catch (Exception $e) {
            $error = 'Error del sistema: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, complete todos los campos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MotoTaxis Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
        }
        .btn {
            border-radius: 8px;
            padding: 12px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
        }
        .password-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .password-toggle:hover {
            color: #007bff !important;
        }
        .input-group-text {
            border-radius: 8px 0 0 8px;
        }
        .password-toggle-container {
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body class="login-body">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h4><i class="fas fa-motorcycle me-2"></i>MotoTaxis Cliente</h4>
                        <p class="mb-0 mt-2">Iniciar Sesión</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" 
                                           required autofocus placeholder="Ingrese su usuario">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="contrasena" name="contrasena" 
                                           required placeholder="Ingrese su contraseña">
                                    <span class="input-group-text password-toggle-container">
                                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                    </span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-sign-in-alt me-2"></i>Ingresar
                            </button>
                        </form>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                Sistema de gestión de API para mototaxis Huanta
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('#togglePassword');
            const passwordInput = document.querySelector('#contrasena');
            
            togglePassword.addEventListener('click', function() {
                // Cambiar el tipo de input
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Cambiar el icono
                if (type === 'password') {
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                } else {
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                }
            });
        });
    </script>
</body>
</html>