<?php
class ClientApi {
    private $conn;
    private $table_name = "client_api";

    public $id;
    public $razon_social;
    public $ruc;
    public $telefono;
    public $correo;
    public $fecha_registro;
    public $estado;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt;
        } catch (Exception $e) {
            error_log("Error en ClientApi::read(): " . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en ClientApi::getById(): " . $e->getMessage());
            return false;
        }
    }

    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     SET razon_social=:razon_social, ruc=:ruc, telefono=:telefono, 
                         correo=:correo, fecha_registro=NOW(), estado=1";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":razon_social", $this->razon_social);
            $stmt->bindParam(":ruc", $this->ruc);
            $stmt->bindParam(":telefono", $this->telefono);
            $stmt->bindParam(":correo", $this->correo);
            
            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en ClientApi::create(): " . $e->getMessage());
            return false;
        }
    }

    public function update() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET razon_social=:razon_social, ruc=:ruc, telefono=:telefono, 
                         correo=:correo, estado=:estado
                     WHERE id=:id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":razon_social", $this->razon_social);
            $stmt->bindParam(":ruc", $this->ruc);
            $stmt->bindParam(":telefono", $this->telefono);
            $stmt->bindParam(":correo", $this->correo);
            $stmt->bindParam(":estado", $this->estado);
            $stmt->bindParam(":id", $this->id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en ClientApi::update(): " . $e->getMessage());
            return false;
        }
    }
}
?>