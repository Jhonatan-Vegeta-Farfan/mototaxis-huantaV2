<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Inicializar conexión a BD si no existe
if (!isset($pdo)) {
    $config_paths = [
        __DIR__ . '/../config/database.php',
        __DIR__ . '/../../config/database.php',
        'config/database.php',
        '../config/database.php'
    ];
    
    $database_loaded = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $database_loaded = true;
            break;
        }
    }
    
    if (!$database_loaded) {
        error_log("No se pudo encontrar config/database.php");
        $pdo = null;
    }
}
?>
