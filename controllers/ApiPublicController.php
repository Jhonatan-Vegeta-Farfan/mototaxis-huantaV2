<?php
// Verificar si los modelos existen antes de incluirlos
$model_files = [
    __DIR__ . '/../models/Mototaxi.php',
    __DIR__ . '/../models/TokenApi.php',
    __DIR__ . '/../models/ClientApi.php',
    __DIR__ . '/../models/CountRequest.php',
    __DIR__ . '/../models/ExternalApiConsumer.php'
];

foreach ($model_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

class ApiPublicController {
    private $mototaxiModel;
    private $tokenApiModel;
    private $clientApiModel;
    private $countRequestModel;
    private $externalApiConsumer;
    private $db;

    public function __construct($db = null) {
        $this->db = $db;
        
        // Inicializar modelos solo si la conexión existe
        if ($db) {
            try {
                $this->mototaxiModel = class_exists('Mototaxi') ? new Mototaxi($db) : null;
                $this->tokenApiModel = class_exists('TokenApi') ? new TokenApi($db) : null;
                $this->clientApiModel = class_exists('ClientApi') ? new ClientApi($db) : null;
                $this->countRequestModel = class_exists('CountRequest') ? new CountRequest($db) : null;
                $this->externalApiConsumer = class_exists('ExternalApiConsumer') ? new ExternalApiConsumer($db) : null;
            } catch (Exception $e) {
                error_log("Error inicializando modelos: " . $e->getMessage());
            }
        }
    }

    // VISTA PÚBLICA DE DOCUMENTACIÓN
    public function index() {
        $view_file = __DIR__ . '/../views/api_public/index.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            $this->mostrarVistaRespaldo();
        }
    }

    // LISTAR MOTOTAXIS (JSON)
    public function listarMototaxis() {
        $this->configurarHeadersJSON();
        
        // Validar token automáticamente
        $tokenValido = $this->validarTokenAutomatico();
        if (!$tokenValido) return;
        
        try {
            $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
            $porPagina = isset($_GET['por_pagina']) ? max(1, intval($_GET['por_pagina'])) : 10;
            
            $mototaxisPaginados = [];
            $totalMototaxis = 0;
            $fuente = 'BD_LOCAL';
            
            // PRIMERO: Obtener datos de la API EXTERNA
            if ($this->externalApiConsumer) {
                $resultadoExterno = $this->externalApiConsumer->listarMototaxisExternos($pagina, $porPagina);
                
                if ($resultadoExterno && isset($resultadoExterno['data'])) {
                    $mototaxisPaginados = $resultadoExterno['data'];
                    $totalMototaxis = $resultadoExterno['paginacion']['total_registros'] ?? count($mototaxisPaginados);
                    $fuente = 'API_EXTERNA';
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Lista de mototaxis obtenida exitosamente desde API externa',
                        'data' => $mototaxisPaginados,
                        'paginacion' => [
                            'pagina_actual' => (int)$pagina,
                            'por_pagina' => (int)$porPagina,
                            'total_registros' => $totalMototaxis,
                            'total_paginas' => ceil($totalMototaxis / $porPagina),
                            'fuente' => $fuente
                        ]
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
            
            // SEGUNDO: Base de datos local
            if ($this->mototaxiModel && $this->db) {
                try {
                    $stmt = $this->mototaxiModel->read();
                    if ($stmt) {
                        $totalMototaxis = $stmt->rowCount();
                        
                        $offset = ($pagina - 1) * $porPagina;
                        $contador = 0;
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            if ($contador >= $offset && $contador < ($offset + $porPagina)) {
                                $mototaxisPaginados[] = $this->formatearDatosMototaxi($row);
                            }
                            $contador++;
                            if ($contador >= ($offset + $porPagina)) break;
                        }
                        
                        $fuente = 'BD_LOCAL';
                    }
                } catch (Exception $e) {
                    error_log("Error obteniendo datos de BD: " . $e->getMessage());
                }
            }
            
            // TERCERO: Datos de prueba
            if (empty($mototaxisPaginados)) {
                $datosPrueba = $this->getDatosPruebaEstaticos();
                $totalMototaxis = count($datosPrueba);
                $offset = ($pagina - 1) * $porPagina;
                $mototaxisPaginados = array_slice($datosPrueba, $offset, $porPagina);
                $fuente = 'DATOS_PRUEBA';
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de mototaxis obtenida exitosamente desde ' . $fuente,
                'data' => $mototaxisPaginados,
                'paginacion' => [
                    'pagina_actual' => (int)$pagina,
                    'por_pagina' => (int)$porPagina,
                    'total_registros' => $totalMototaxis,
                    'total_paginas' => ceil($totalMototaxis / $porPagina),
                    'fuente' => $fuente
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener mototaxis: ' . $e->getMessage()
            ]);
        }
    }

    // BUSCAR MOTOTAXI POR NÚMERO ASIGNADO O PLACA DE RODAJE (JSON)
    public function buscarMototaxi() {
        $this->configurarHeadersJSON();
        
        // Validar token automáticamente
        $tokenValido = $this->validarTokenAutomatico();
        if (!$tokenValido) return;
        
        try {
            $numero = $_GET['numero'] ?? '';
            $placa = $_GET['placa'] ?? '';
            
            if (empty($numero) && empty($placa)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Parámetro "numero" o "placa" requerido para la búsqueda'
                ]);
                return;
            }
            
            $mototaxi = null;
            $fuente = 'BD_LOCAL';
            $tipoBusqueda = '';
            $valorBuscado = '';
            
            // Determinar tipo de búsqueda
            if (!empty($numero)) {
                $tipoBusqueda = 'numero';
                $valorBuscado = $numero;
                
                // Normalizar número: agregar 0 si es un solo dígito (1-9)
                $numeroNormalizado = $this->normalizarNumeroMototaxi($numero);
                
                // PRIMERO: API EXTERNA
                if ($this->externalApiConsumer) {
                    $mototaxiExterno = $this->externalApiConsumer->buscarMototaxiExterno($numeroNormalizado);
                    
                    if ($mototaxiExterno) {
                        $mototaxi = $mototaxiExterno;
                        $fuente = 'API_EXTERNA';
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Mototaxi encontrado exitosamente en API externa',
                            'data' => $mototaxi,
                            'metadata' => [
                                'fecha_consulta' => date('Y-m-d H:i:s'),
                                'numero_buscado' => $numero,
                                'numero_normalizado' => $numeroNormalizado,
                                'tipo_busqueda' => $tipoBusqueda,
                                'total_resultados' => 1,
                                'fuente' => $fuente
                            ]
                        ], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                }
                
                // SEGUNDO: Base de datos local
                if ($this->db) {
                    try {
                        // Buscar con número normalizado primero
                        $query = "SELECT m.*, e.razon_social as empresa, e.ruc as ruc_empresa,
                                         e.representante_legal as representante_empresa
                                 FROM mototaxis m 
                                 LEFT JOIN empresas e ON m.id_empresa = e.id 
                                 WHERE m.numero_asignado = ?";
                        
                        $stmt = $this->db->prepare($query);
                        $stmt->bindParam(1, $numeroNormalizado);
                        $stmt->execute();
                        
                        $mototaxi = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Si no se encuentra con número normalizado, buscar con el original
                        if (!$mototaxi && $numeroNormalizado !== $numero) {
                            $stmt->bindParam(1, $numero);
                            $stmt->execute();
                            $mototaxi = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                        
                        if ($mototaxi) {
                            $fuente = 'BD_LOCAL';
                        }
                    } catch (Exception $e) {
                        error_log("Error en búsqueda BD: " . $e->getMessage());
                    }
                }
                
            } elseif (!empty($placa)) {
                $tipoBusqueda = 'placa';
                $valorBuscado = $placa;
                
                // Normalizar placa: formatear a 6 caracteres + guión
                $placaNormalizada = $this->normalizarPlacaRodaje($placa);
                
                // PRIMERO: API EXTERNA
                if ($this->externalApiConsumer) {
                    // Para búsqueda por placa en API externa, necesitaríamos un endpoint específico
                    // Por ahora buscamos en BD local
                }
                
                // SEGUNDO: Base de datos local
                if ($this->db) {
                    try {
                        $query = "SELECT m.*, e.razon_social as empresa, e.ruc as ruc_empresa,
                                         e.representante_legal as representante_empresa
                                 FROM mototaxis m 
                                 LEFT JOIN empresas e ON m.id_empresa = e.id 
                                 WHERE m.placa_rodaje = ? OR m.placa_rodaje = ?";
                        
                        $stmt = $this->db->prepare($query);
                        $stmt->bindParam(1, $placa);
                        $stmt->bindParam(2, $placaNormalizada);
                        $stmt->execute();
                        
                        $mototaxi = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($mototaxi) {
                            $fuente = 'BD_LOCAL';
                        }
                    } catch (Exception $e) {
                        error_log("Error en búsqueda BD por placa: " . $e->getMessage());
                    }
                }
            }
            
            // TERCERO: Datos de prueba
            if (!$mototaxi) {
                $datosPrueba = $this->getDatosPruebaEstaticos();
                foreach ($datosPrueba as $mt) {
                    if ($tipoBusqueda === 'numero') {
                        // Buscar con número normalizado y original
                        if ($mt['numero_asignado'] === $numeroNormalizado || $mt['numero_asignado'] === $numero) {
                            $mototaxi = $mt;
                            $fuente = 'DATOS_PRUEBA';
                            break;
                        }
                    } elseif ($tipoBusqueda === 'placa') {
                        // Buscar con placa normalizada y original
                        if ($mt['placa_rodaje'] === $placa || $mt['placa_rodaje'] === $placaNormalizada) {
                            $mototaxi = $mt;
                            $fuente = 'DATOS_PRUEBA';
                            break;
                        }
                    }
                }
            }
            
            if (!$mototaxi) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Mototaxi no encontrado' . 
                                ($tipoBusqueda === 'numero' ? ' con el número: ' . $numero : 
                                 ($tipoBusqueda === 'placa' ? ' con la placa: ' . $placa : '')),
                    'sugerencia' => $this->getSugerenciaBusqueda($tipoBusqueda, $valorBuscado)
                ]);
                return;
            }
            
            // Formatear datos para respuesta
            $mototaxiFormateado = $this->formatearDatosMototaxi($mototaxi);
            
            echo json_encode([
                'success' => true,
                'message' => 'Mototaxi encontrado exitosamente en ' . $fuente,
                'data' => $mototaxiFormateado,
                'metadata' => [
                    'fecha_consulta' => date('Y-m-d H:i:s'),
                    'tipo_busqueda' => $tipoBusqueda,
                    'valor_buscado' => $valorBuscado,
                    'valor_normalizado' => $tipoBusqueda === 'numero' ? $this->normalizarNumeroMototaxi($numero) : $this->normalizarPlacaRodaje($placa),
                    'total_resultados' => 1,
                    'fuente' => $fuente
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error en la búsqueda: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Normaliza el número de mototaxi agregando 0 si es necesario
     */
    private function normalizarNumeroMototaxi($numero) {
        // Si es un número del 1 al 9 sin cero, agregar el cero
        if (preg_match('/^\d{1,2}$/', $numero)) {
            $numeroInt = intval($numero);
            if ($numeroInt >= 1 && $numeroInt <= 9) {
                return '0' . $numero;
            }
        }
        return $numero;
    }

    /**
     * Normaliza la placa de rodaje al formato 6 caracteres + guión
     */
    private function normalizarPlacaRodaje($placa) {
        // Remover espacios y convertir a mayúsculas
        $placa = strtoupper(trim($placa));
        
        // Si ya tiene el formato correcto, retornar tal cual
        if (preg_match('/^[A-Z0-9]{3}-[A-Z0-9]{3}$/', $placa)) {
            return $placa;
        }
        
        // Si tiene 6 caracteres sin guión, agregar guión en la posición 3
        if (preg_match('/^[A-Z0-9]{6}$/', $placa)) {
            return substr($placa, 0, 3) . '-' . substr($placa, 3, 3);
        }
        
        // Si tiene otros formatos, intentar normalizar
        $placa = preg_replace('/[^A-Z0-9]/', '', $placa); // Remover caracteres no alfanuméricos
        if (strlen($placa) >= 6) {
            return substr($placa, 0, 3) . '-' . substr($placa, 3, 3);
        }
        
        // Si no se puede normalizar, retornar original
        return $placa;
    }

    /**
     * Genera sugerencias de búsqueda según el tipo
     */
    private function getSugerenciaBusqueda($tipo, $valor) {
        if ($tipo === 'numero') {
            return 'Verifique el número e intente nuevamente. Números de ejemplo: 1, 01, 123, MT-001';
        } elseif ($tipo === 'placa') {
            return 'Verifique la placa e intente nuevamente. Formatos aceptados: ABC123, ABC-123';
        }
        return 'Verifique los datos e intente nuevamente';
    }

    // VALIDAR TOKEN (mantenido para compatibilidad)
    public function validarTokenEndpoint() {
        $this->configurarHeadersJSON();
        
        try {
            $headers = getallheaders();
            $token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            $token = str_replace('Bearer ', '', $token);
            
            if (empty($token)) {
                $token = $_GET['token'] ?? '';
            }
            
            if (empty($token)) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => '❌ Token de acceso requerido'
                ]);
                return;
            }
            
            $tokenData = $this->obtenerTokenDeBD($token);
            
            if (!$tokenData) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => '❌ Token no existe'
                ]);
                return;
            }
            
            if (isset($tokenData['estado']) && !$tokenData['estado']) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => '❌ Token inactivo - Contacte al administrador'
                ]);
                return;
            }
            
            if ($this->countRequestModel && isset($tokenData['id'])) {
                $this->registrarRequest($tokenData['id'], 'consulta_api');
            }
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Token válido',
                'data' => [
                    'token' => [
                        'id' => $tokenData['id'] ?? null,
                        'token' => $tokenData['token'] ?? null,
                        'descripcion' => $tokenData['descripcion'] ?? null,
                        'estado' => (bool)($tokenData['estado'] ?? true),
                        'fecha_registro' => $tokenData['fecha_registro'] ?? date('Y-m-d H:i:s')
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error validando token: ' . $e->getMessage()
            ]);
        }
    }

    // MÉTODOS PRIVADOS ACTUALIZADOS
    private function configurarHeadersJSON() {
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }

    /**
     * VALIDACIÓN AUTOMÁTICA DE TOKEN
     * Busca automáticamente tokens activos en la base de datos
     */
    private function validarTokenAutomatico() {
        try {
            // Obtener tokens activos de la base de datos
            $tokensActivos = $this->obtenerTokensActivos();
            
            if (empty($tokensActivos)) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => '❌ No hay tokens activos disponibles en el sistema'
                ]);
                return false;
            }
            
            // Usar el primer token activo encontrado
            $tokenData = $tokensActivos[0];
            
            if (isset($tokenData['estado']) && !$tokenData['estado']) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => '❌ Token inactivo - Contacte al administrador'
                ]);
                return false;
            }
            
            // Registrar la solicitud
            if ($this->countRequestModel && isset($tokenData['id'])) {
                $this->registrarRequest($tokenData['id'], 'consulta_api_automatica');
            }
            
            return true;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error en autenticación automática: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene todos los tokens activos de la base de datos
     */
    private function obtenerTokensActivos() {
        $tokensActivos = [];
        
        // Intentar obtener de la base de datos
        if ($this->tokenApiModel && $this->db) {
            try {
                $stmt = $this->tokenApiModel->read();
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if ($row['estado'] == 1) {
                            $tokensActivos[] = $row;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error obteniendo tokens activos: " . $e->getMessage());
            }
        }
        
        return $tokensActivos;
    }

    /**
     * Obtiene un token específico de la base de datos
     */
    private function obtenerTokenDeBD($token) {
        if ($this->tokenApiModel && $this->db) {
            return $this->tokenApiModel->getByToken($token);
        }
        return false;
    }

    private function registrarRequest($tokenId, $tipo) {
        try {
            if ($this->countRequestModel && $this->db) {
                $this->countRequestModel->id_token_api = $tokenId;
                $this->countRequestModel->tipo = $tipo;
                $this->countRequestModel->create();
            }
        } catch (Exception $e) {
            error_log("Error registrando request: " . $e->getMessage());
        }
    }

    private function formatearDatosMototaxi($mototaxi) {
        return [
            'id' => $mototaxi['id'] ?? null,
            'numero_asignado' => $mototaxi['numero_asignado'] ?? '',
            'nombre_completo' => $mototaxi['nombre_completo'] ?? '',
            'dni' => $mototaxi['dni'] ?? '',
            'direccion' => $mototaxi['direccion'] ?? '',
            'placa_rodaje' => $mototaxi['placa_rodaje'] ?? '',
            'anio_fabricacion' => $mototaxi['anio_fabricacion'] ?? '',
            'marca' => $mototaxi['marca'] ?? '',
            'numero_motor' => $mototaxi['numero_motor'] ?? '',
            'tipo_motor' => $mototaxi['tipo_motor'] ?? '',
            'serie' => $mototaxi['serie'] ?? '',
            'color' => $mototaxi['color'] ?? '',
            'fecha_registro' => $mototaxi['fecha_registro'] ?? '',
            'id_empresa' => $mototaxi['id_empresa'] ?? null,
            'empresa' => [
                'razon_social' => $mototaxi['empresa'] ?? ($mototaxi['razon_social'] ?? ''),
                'ruc' => $mototaxi['ruc_empresa'] ?? ($mototaxi['ruc'] ?? ''),
                'representante_legal' => $mototaxi['representante_empresa'] ?? ($mototaxi['representante_legal'] ?? '')
            ],
            'estado_registro' => $mototaxi['estado_registro'] ?? 'ACTIVO',
            'fecha_actualizacion' => date('Y-m-d H:i:s'),
            'fuente' => $mototaxi['fuente'] ?? 'BD_LOCAL'
        ];
    }

    private function getDatosPruebaEstaticos() {
        return [
            [
                'id' => 1,
                'numero_asignado' => '01',
                'nombre_completo' => 'Juan Pérez García',
                'dni' => '12345678',
                'direccion' => 'Av. Principal 123, Huanta',
                'placa_rodaje' => 'ABC-123',
                'anio_fabricacion' => '2020',
                'marca' => 'Honda',
                'numero_motor' => 'M123456',
                'tipo_motor' => '4 Tiempos',
                'serie' => 'S789012',
                'color' => 'Rojo',
                'fecha_registro' => '2023-01-15',
                'empresa' => [
                    'razon_social' => 'Transportes Huanta SAC',
                    'ruc' => '20123456781',
                    'representante_legal' => 'Carlos Rodríguez'
                ],
                'estado_registro' => 'ACTIVO',
                'fecha_actualizacion' => date('Y-m-d H:i:s'),
                'fuente' => 'DATOS_PRUEBA'
            ],
            [
                'id' => 2,
                'numero_asignado' => '02',
                'nombre_completo' => 'María López Hernández',
                'dni' => '87654321',
                'direccion' => 'Jr. Los Olivos 456, Huanta',
                'placa_rodaje' => 'DEF-456',
                'anio_fabricacion' => '2021',
                'marca' => 'Yamaha',
                'numero_motor' => 'M654321',
                'tipo_motor' => '4 Tiempos',
                'serie' => 'S345678',
                'color' => 'Azul',
                'fecha_registro' => '2023-02-20',
                'empresa' => [
                    'razon_social' => 'Transportes Huanta SAC',
                    'ruc' => '20123456781',
                    'representante_legal' => 'Carlos Rodríguez'
                ],
                'estado_registro' => 'ACTIVO',
                'fecha_actualizacion' => date('Y-m-d H:i:s'),
                'fuente' => 'DATOS_PRUEBA'
            ],
            [
                'id' => 3,
                'numero_asignado' => '03',
                'nombre_completo' => 'Carlos Ramírez Torres',
                'dni' => '45678912',
                'direccion' => 'Av. Libertad 789, Huanta',
                'placa_rodaje' => 'GHI-789',
                'anio_fabricacion' => '2019',
                'marca' => 'Suzuki',
                'numero_motor' => 'M987654',
                'tipo_motor' => '2 Tiempos',
                'serie' => 'S123456',
                'color' => 'Verde',
                'fecha_registro' => '2023-03-10',
                'empresa' => [
                    'razon_social' => 'MotoServicios EIRL',
                    'ruc' => '20456789123',
                    'representante_legal' => 'Ana Martínez'
                ],
                'estado_registro' => 'ACTIVO',
                'fecha_actualizacion' => date('Y-m-d H:i:s'),
                'fuente' => 'DATOS_PRUEBA'
            ]
        ];
    }

    private function mostrarVistaRespaldo() {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>API Mototaxis - Error</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .error { color: #d63031; background: #ffeaa7; padding: 20px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <h1>API de Mototaxis</h1>
            <div class="error">
                <h3>Error: Vista no encontrada</h3>
                <p>La interfaz de la API no está disponible temporalmente.</p>
                <p>Puede usar los endpoints JSON directamente:</p>
                <ul>
                    <li><code>/api.php?action=validar_token&token=TOKEN</code></li>
                    <li><code>/api.php?action=buscar&numero=01</code></li>
                    <li><code>/api.php?action=buscar&placa=ABC-123</code></li>
                    <li><code>/api.php?action=listar&pagina=1</code></li>
                </ul>
                <p><strong>Nota:</strong> El sistema ahora usa autenticación automática con tokens de la base de datos.</p>
                <p><strong>Nuevo:</strong> Búsqueda por número (1-9 sin cero) y por placa de rodaje.</p>
            </div>
        </body>
        </html>';
    }

    // Métodos adicionales para completar la clase
    public function verificarApiExterna() {
        $this->configurarHeadersJSON();
        
        try {
            $apiDisponible = false;
            $detalles = ['conexion_exitosa' => false];
            
            if ($this->externalApiConsumer) {
                $resultado = $this->externalApiConsumer->probarConexionAPI();
                $apiDisponible = $resultado['conexion_exitosa'] ?? false;
                $detalles = $resultado;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'api_externa_disponible' => $apiDisponible,
                    'api_externa_url' => 'https://mototaxis-huanta.dpweb2024.com/',
                    'fecha_verificacion' => date('Y-m-d H:i:s'),
                    'detalles' => $detalles
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error verificando API externa: ' . $e->getMessage()
            ]);
        }
    }

    public function obtenerDatosApiExterna() {
        $this->configurarHeadersJSON();
        
        // Validar token automáticamente
        $tokenValido = $this->validarTokenAutomatico();
        if (!$tokenValido) return;
        
        try {
            $datos = [];
            if ($this->externalApiConsumer) {
                $datos = $this->externalApiConsumer->obtenerDatosDirectosAPI();
            }
            
            if ($datos) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Datos obtenidos de API externa',
                    'data' => $datos
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se pudieron obtener datos de la API externa',
                    'data' => []
                ], JSON_UNESCAPED_UNICODE);
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error obteniendo datos de API externa: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Nuevo método: Obtener información de tokens activos
     */
    public function obtenerTokensActivosEndpoint() {
        $this->configurarHeadersJSON();
        
        try {
            $tokensActivos = $this->obtenerTokensActivos();
            
            echo json_encode([
                'success' => true,
                'message' => 'Tokens activos obtenidos exitosamente',
                'data' => [
                    'total_tokens' => count($tokensActivos),
                    'tokens' => $tokensActivos
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error obteniendo tokens activos: ' . $e->getMessage()
            ]);
        }
    }
}
?>