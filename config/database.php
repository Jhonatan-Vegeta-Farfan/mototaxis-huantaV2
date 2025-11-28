<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'prograp_mototaxis_huanta';
    private $username = 'prograp_mototaxis_huanta';
    private $password = '47530217vegeta';
    public $conn;

    // Detectar protocolo automáticamente
    private $protocol;
    private $api_base_url;
    private $api_endpoint;

    public function __construct() {
        // Detectar si es HTTPS o HTTP
        $this->protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $current_domain = $_SERVER['HTTP_HOST'];
        
        // URLs para la API
        $this->api_base_url = $this->protocol . '://mototaxis-huanta.dpweb2024.com/';
        $this->api_endpoint = $this->protocol . '://mototaxis-huanta.dpweb2024.com/api.php';

        $this->getConnection();
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            global $pdo;
            $pdo = $this->conn;
            
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            return false;
        }
        return $this->conn;
    }

    public function getApiBaseUrl() {
        return $this->api_base_url;
    }

    public function getApiEndpoint() {
        return $this->api_endpoint;
    }

    public function consumeExternalAPI($params = []) {
        $url = $this->api_endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'MotoTaxis-API-Consumer/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Error cURL API externa: " . $error);
            return false;
        }

        if ($http_code === 200 && !empty($response)) {
            return json_decode($response, true);
        }

        return false;
    }

    /**
     * Obtiene un token activo automáticamente de la base de datos
     */
    public function getActiveToken() {
        try {
            $query = "SELECT token FROM tokens_api WHERE estado = 1 ORDER BY id DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['token'] : null;
            
        } catch (Exception $e) {
            error_log("Error obteniendo token activo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todos los tokens activos
     */
    public function getAllActiveTokens() {
        try {
            $query = "SELECT token FROM tokens_api WHERE estado = 1 ORDER BY id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $tokens = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tokens[] = $row['token'];
            }
            return $tokens;
            
        } catch (Exception $e) {
            error_log("Error obteniendo tokens activos: " . $e->getMessage());
            return [];
        }
    }
}

// Crear instancia global
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    error_log("Error inicializando base de datos: " . $e->getMessage());
    $pdo = null;
}
?>