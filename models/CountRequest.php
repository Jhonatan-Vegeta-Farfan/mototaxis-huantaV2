<?php
class CountRequest {
    private $conn;
    private $table_name = "count_request";

    public $id;
    public $id_token_api;
    public $tipo;
    public $fecha;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     SET id_token_api=:id_token_api, tipo=:tipo, fecha=NOW()";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":id_token_api", $this->id_token_api);
            $stmt->bindParam(":tipo", $this->tipo);
            
            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en CountRequest::create(): " . $e->getMessage());
            return false;
        }
    }

    public function getStatsByToken($id_token_api, $days = 30) {
        try {
            $query = "SELECT DATE(fecha) as fecha, COUNT(*) as total 
                      FROM " . $this->table_name . " 
                      WHERE id_token_api = ? AND fecha >= DATE_SUB(NOW(), INTERVAL ? DAY)
                      GROUP BY DATE(fecha) 
                      ORDER BY fecha DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id_token_api);
            $stmt->bindParam(2, $days);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en CountRequest::getStatsByToken(): " . $e->getMessage());
            return [];
        }
    }

    public function getTotalRequestsByToken($id_token_api) {
        try {
            $query = "SELECT COUNT(*) as total 
                      FROM " . $this->table_name . " 
                      WHERE id_token_api = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id_token_api);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error en CountRequest::getTotalRequestsByToken(): " . $e->getMessage());
            return 0;
        }
    }
}
?>