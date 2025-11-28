<?php
// config/development.php
define('BASE_URL', 'http://localhost/mototaxis-cliente');
define('ENVIRONMENT', 'development');

// Configuración de base de datos para desarrollo
$development_config = [
    'host' => 'localhost',
    'dbname' => 'dpwebcom_mototaxis_huanta',
    'username' => 'root',
    'password' => ''
];

// Mostrar errores en desarrollo
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

/**
 * Función para verificar tokens automáticamente en desarrollo
 */
function checkAutoAuth() {
    global $development_config;
    
    try {
        $pdo = new PDO(
            "mysql:host={$development_config['host']};dbname={$development_config['dbname']};charset=utf8mb4",
            $development_config['username'],
            $development_config['password']
        );
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM tokens_api WHERE estado = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            error_log("Desarrollo: Se encontraron {$result['total']} tokens activos para autenticación automática");
        } else {
            error_log("Desarrollo: No hay tokens activos para autenticación automática");
        }
        
    } catch (Exception $e) {
        error_log("Desarrollo: Error verificando tokens automáticos: " . $e->getMessage());
    }
}

// Verificar autenticación automática al cargar en desarrollo
if (ENVIRONMENT === 'development') {
    checkAutoAuth();
}
?>