<?php
class TokenApi {
    private $conn;
    private $table_name = "tokens_api";

    public $id;
    public $token;
    public $descripcion;
    public $estado;
    public $fecha_registro;
    public $id_client_api;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByToken($token) {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "SELECT * FROM " . $this->table_name . " WHERE token = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $token);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en TokenApi::getByToken(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todos los tokens activos
     */
    public function getActiveTokens() {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "SELECT * FROM " . $this->table_name . " WHERE estado = 1 ORDER BY id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $tokens = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tokens[] = $row;
            }
            return $tokens;
            
        } catch (Exception $e) {
            error_log("Error en TokenApi::getActiveTokens(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el primer token activo disponible
     */
    public function getFirstActiveToken() {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "SELECT * FROM " . $this->table_name . " WHERE estado = 1 ORDER BY id DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
            
        } catch (Exception $e) {
            error_log("Error en TokenApi::getFirstActiveToken(): " . $e->getMessage());
            return false;
        }
    }

    public function read() {
        try {
            $query = "SELECT t.*, c.razon_social as cliente 
                      FROM " . $this->table_name . " t 
                      LEFT JOIN client_api c ON t.id_client_api = c.id 
                      ORDER BY t.id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt;
        } catch (Exception $e) {
            error_log("Error en TokenApi::read(): " . $e->getMessage());
            return false;
        }
    }

    public function toggleStatus($id) {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "SELECT estado FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                $nuevo_estado = $current['estado'] ? 0 : 1;
                
                $query = "UPDATE " . $this->table_name . " SET estado = ? WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $nuevo_estado);
                $stmt->bindParam(2, $id);
                
                if ($stmt->execute()) {
                    return $nuevo_estado;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en TokenApi::toggleStatus(): " . $e->getMessage());
            return false;
        }
    }

    public function updateStatus($id, $estado) {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "UPDATE " . $this->table_name . " SET estado = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $estado);
            $stmt->bindParam(2, $id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en TokenApi::updateStatus(): " . $e->getMessage());
            return false;
        }
    }

    public function create($token, $descripcion = '', $id_client_api = null) {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "INSERT INTO " . $this->table_name . " 
                     (token, descripcion, id_client_api, fecha_registro, estado) 
                     VALUES (?, ?, ?, NOW(), 1)";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$token, $descripcion, $id_client_api]);
        } catch (Exception $e) {
            error_log("Error en TokenApi::create(): " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Error en TokenApi::delete(): " . $e->getMessage());
            return false;
        }
    }

    public function generateToken($length = 32) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $token . '-' . uniqid();
    }

    /**
     * Verifica si hay tokens activos disponibles
     */
    public function hasActiveTokens() {
        try {
            if (!$this->conn) {
                throw new Exception("Conexión a BD no disponible");
            }

            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE estado = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] > 0;
            
        } catch (Exception $e) {
            error_log("Error en TokenApi::hasActiveTokens(): " . $e->getMessage());
            return false;
        }
    }
}
?>