<?php
class Mototaxi {
    private $conn;
    private $table_name = "mototaxis";

    public $id;
    public $numero_asignado;
    public $nombre_completo;
    public $dni;
    public $direccion;
    public $placa_rodaje;
    public $anio_fabricacion;
    public $marca;
    public $numero_motor;
    public $tipo_motor;
    public $serie;
    public $color;
    public $fecha_registro;
    public $id_empresa;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        try {
            // Verificar si la tabla existe
            $check_table = $this->conn->query("SHOW TABLES LIKE 'mototaxis'");
            if ($check_table->rowCount() == 0) {
                throw new Exception("La tabla 'mototaxis' no existe en la base de datos");
            }

            $query = "SELECT m.*, e.razon_social as empresa, e.ruc as ruc_empresa, 
                             e.representante_legal as representante_empresa
                      FROM " . $this->table_name . " m 
                      LEFT JOIN empresas e ON m.id_empresa = e.id 
                      ORDER BY m.id DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt;
        } catch (Exception $e) {
            error_log("Error en Mototaxi::read(): " . $e->getMessage());
            throw $e;
        }
    }

    public function getByNumero($numero_asignado) {
        try {
            $query = "SELECT m.*, e.razon_social as empresa, e.ruc as ruc_empresa,
                             e.representante_legal as representante_empresa
                      FROM " . $this->table_name . " m 
                      LEFT JOIN empresas e ON m.id_empresa = e.id 
                      WHERE m.numero_asignado = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $numero_asignado);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en Mototaxi::getByNumero(): " . $e->getMessage());
            return false;
        }
    }

    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     SET numero_asignado=:numero_asignado, nombre_completo=:nombre_completo, 
                         dni=:dni, direccion=:direccion, placa_rodaje=:placa_rodaje, 
                         anio_fabricacion=:anio_fabricacion, marca=:marca, 
                         numero_motor=:numero_motor, tipo_motor=:tipo_motor, 
                         serie=:serie, color=:color, fecha_registro=NOW(), 
                         id_empresa=:id_empresa";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":numero_asignado", $this->numero_asignado);
            $stmt->bindParam(":nombre_completo", $this->nombre_completo);
            $stmt->bindParam(":dni", $this->dni);
            $stmt->bindParam(":direccion", $this->direccion);
            $stmt->bindParam(":placa_rodaje", $this->placa_rodaje);
            $stmt->bindParam(":anio_fabricacion", $this->anio_fabricacion);
            $stmt->bindParam(":marca", $this->marca);
            $stmt->bindParam(":numero_motor", $this->numero_motor);
            $stmt->bindParam(":tipo_motor", $this->tipo_motor);
            $stmt->bindParam(":serie", $this->serie);
            $stmt->bindParam(":color", $this->color);
            $stmt->bindParam(":id_empresa", $this->id_empresa);
            
            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en Mototaxi::create(): " . $e->getMessage());
            return false;
        }
    }

    public function update() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET numero_asignado=:numero_asignado, nombre_completo=:nombre_completo, 
                         dni=:dni, direccion=:direccion, placa_rodaje=:placa_rodaje, 
                         anio_fabricacion=:anio_fabricacion, marca=:marca, 
                         numero_motor=:numero_motor, tipo_motor=:tipo_motor, 
                         serie=:serie, color=:color, id_empresa=:id_empresa
                     WHERE id=:id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":numero_asignado", $this->numero_asignado);
            $stmt->bindParam(":nombre_completo", $this->nombre_completo);
            $stmt->bindParam(":dni", $this->dni);
            $stmt->bindParam(":direccion", $this->direccion);
            $stmt->bindParam(":placa_rodaje", $this->placa_rodaje);
            $stmt->bindParam(":anio_fabricacion", $this->anio_fabricacion);
            $stmt->bindParam(":marca", $this->marca);
            $stmt->bindParam(":numero_motor", $this->numero_motor);
            $stmt->bindParam(":tipo_motor", $this->tipo_motor);
            $stmt->bindParam(":serie", $this->serie);
            $stmt->bindParam(":color", $this->color);
            $stmt->bindParam(":id_empresa", $this->id_empresa);
            $stmt->bindParam(":id", $this->id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en Mototaxi::update(): " . $e->getMessage());
            return false;
        }
    }

    // Método alternativo para obtener datos de prueba si la tabla no existe
    public function getDatosPrueba() {
        return [
            [
                'id' => 1,
                'numero_asignado' => 'MT-001',
                'nombre_completo' => 'Juan Pérez García',
                'dni' => '12345678',
                'direccion' => 'Av. Principal 123',
                'placa_rodaje' => 'ABC-123',
                'anio_fabricacion' => '2020',
                'marca' => 'Honda',
                'numero_motor' => 'M123456',
                'tipo_motor' => '4 Tiempos',
                'serie' => 'S789012',
                'color' => 'Rojo',
                'fecha_registro' => '2023-01-15',
                'id_empresa' => 1,
                'empresa' => 'Transportes Huanta SAC',
                'ruc_empresa' => '20123456781',
                'representante_empresa' => 'Carlos Rodríguez'
            ],
            [
                'id' => 2,
                'numero_asignado' => 'MT-002',
                'nombre_completo' => 'María López Hernández',
                'dni' => '87654321',
                'direccion' => 'Jr. Secundaria 456',
                'placa_rodaje' => 'DEF-456',
                'anio_fabricacion' => '2021',
                'marca' => 'Yamaha',
                'numero_motor' => 'M654321',
                'tipo_motor' => '4 Tiempos',
                'serie' => 'S345678',
                'color' => 'Azul',
                'fecha_registro' => '2023-02-20',
                'id_empresa' => 1,
                'empresa' => 'Transportes Huanta SAC',
                'ruc_empresa' => '20123456781',
                'representante_empresa' => 'Carlos Rodríguez'
            ]
        ];
    }
}
?>