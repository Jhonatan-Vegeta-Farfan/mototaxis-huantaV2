<?php
/**
 * Script de migración para autenticación automática
 * Este script verifica y prepara la base de datos para el nuevo sistema
 */

session_start();
if (!isset($_SESSION['usuario_id'])) {
    die('Acceso denegado');
}

require_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<h2>Migración a Autenticación Automática</h2>";
    echo "<pre>";
    
    // Verificar si la tabla tokens_api existe
    $check_table = $pdo->query("SHOW TABLES LIKE 'tokens_api'");
    if ($check_table->rowCount() == 0) {
        echo "❌ La tabla 'tokens_api' no existe.\n";
        echo "Por favor, crea la tabla primero.\n";
        exit;
    }
    
    echo "✅ Tabla 'tokens_api' encontrada.\n";
    
    // Contar tokens activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tokens_api WHERE estado = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_activos = $result['total'];
    
    echo "📊 Tokens activos encontrados: {$total_activos}\n\n";
    
    if ($total_activos > 0) {
        // Mostrar tokens activos
        $stmt = $pdo->query("SELECT id, token, descripcion FROM tokens_api WHERE estado = 1 ORDER BY id DESC");
        echo "Tokens activos disponibles:\n";
        echo str_repeat("-", 50) . "\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']} | Descripción: {$row['descripcion']}\n";
            echo "Token: {$row['token']}\n";
            echo str_repeat("-", 50) . "\n";
        }
        
        echo "\n✅ El sistema está listo para autenticación automática.\n";
        echo "Los siguientes endpoints ahora funcionan sin token:\n";
        echo "- /api.php?action=listar\n";
        echo "- /api.php?action=buscar&numero=MT-001\n";
        echo "- /api.php?action=obtener_datos_api\n";
        
    } else {
        echo "❌ No hay tokens activos en la base de datos.\n";
        echo "Para usar la autenticación automática, necesitas al menos un token activo.\n";
        echo "Puedes activar tokens existentes o crear nuevos en el panel de administración.\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Migración completada.\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
}
?>