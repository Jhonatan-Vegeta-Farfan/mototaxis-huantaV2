<?php
class ExternalApiConsumer {
    private $db;
    private $api_base_url;
    private $api_endpoint;

    public function __construct($db) {
        $this->db = $db;
        $this->api_base_url = 'https://mototaxis-huanta.dpweb2024.com/';
        $this->api_endpoint = 'https://mototaxis-huanta.dpweb2024.com/api.php';
    }

    /**
     * Obtiene un token activo automáticamente para las consultas externas
     */
    private function obtenerTokenAutomatico() {
        try {
            if ($this->db) {
                $query = "SELECT token FROM tokens_api WHERE estado = 1 ORDER BY id DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['token'] : null;
            }
            return null;
        } catch (Exception $e) {
            error_log("Error obteniendo token automático: " . $e->getMessage());
            return null;
        }
    }

    public function buscarMototaxiExterno($numero_asignado) {
        try {
            // Obtener token automáticamente
            $token = $this->obtenerTokenAutomatico();
            if (!$token) {
                error_log("No se pudo obtener token automático para consulta externa");
                return false;
            }

            $params = [
                'numero' => $numero_asignado,
                'token' => $token
            ];
            $url = $this->api_endpoint . '?' . http_build_query($params);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'MotoTaxis-Cliente-API/1.0',
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log("Error cURL al consumir API externa: " . $error);
                return false;
            }

            if ($http_code === 200 && !empty($response)) {
                $data = json_decode($response, true);
                
                if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                    return $this->formatearDatosExternos($data['data']);
                }
            }

            return false;

        } catch (Exception $e) {
            error_log("Error en ExternalApiConsumer::buscarMototaxiExterno: " . $e->getMessage());
            return false;
        }
    }

    public function listarMototaxisExternos($pagina = 1, $porPagina = 10) {
        try {
            // Obtener token automáticamente
            $token = $this->obtenerTokenAutomatico();
            if (!$token) {
                error_log("No se pudo obtener token automático para listar externos");
                return false;
            }

            $params = [
                'token' => $token
            ];
            $url = $this->api_endpoint . '?' . http_build_query($params);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'MotoTaxis-Cliente-API/1.0',
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log("Error cURL al listar desde API externa: " . $error);
                return false;
            }

            if ($http_code === 200 && !empty($response)) {
                $data = json_decode($response, true);
                
                if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                    $mototaxis_formateados = [];
                    foreach ($data['data'] as $mototaxi) {
                        $mototaxis_formateados[] = $this->formatearDatosExternos($mototaxi);
                    }
                    
                    // Aplicar paginación manual
                    $total = count($mototaxis_formateados);
                    $offset = ($pagina - 1) * $porPagina;
                    $datos_paginados = array_slice($mototaxis_formateados, $offset, $porPagina);
                    
                    return [
                        'data' => $datos_paginados,
                        'paginacion' => [
                            'pagina_actual' => $pagina,
                            'por_pagina' => $porPagina,
                            'total_registros' => $total,
                            'total_paginas' => ceil($total / $porPagina)
                        ]
                    ];
                }
            }

            return false;

        } catch (Exception $e) {
            error_log("Error en ExternalApiConsumer::listarMototaxisExternos: " . $e->getMessage());
            return false;
        }
    }

    // ... (el resto de los métodos se mantienen igual)
    public function obtenerDatosDirectosAPI() {
        try {
            // Obtener token automáticamente
            $token = $this->obtenerTokenAutomatico();
            if (!$token) {
                error_log("No se pudo obtener token automático para datos directos");
                return false;
            }

            $params = ['token' => $token];
            $url = $this->api_endpoint . '?' . http_build_query($params);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'MotoTaxis-Cliente-API/1.0',
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log("Error cURL al obtener datos directos: " . $error);
                return false;
            }

            if ($http_code === 200 && !empty($response)) {
                $data = json_decode($response, true);
                return $data;
            }

            return false;

        } catch (Exception $e) {
            error_log("Error en ExternalApiConsumer::obtenerDatosDirectosAPI: " . $e->getMessage());
            return false;
        }
    }

    private function formatearDatosExternos($datosExternos) {
        // ... (método sin cambios)
        if (isset($datosExternos['numero_asignado'])) {
            return [
                'id' => $datosExternos['id'] ?? null,
                'numero_asignado' => $datosExternos['numero_asignado'] ?? '',
                'nombre_completo' => $datosExternos['nombre_completo'] ?? '',
                'dni' => $datosExternos['dni'] ?? '',
                'direccion' => $datosExternos['direccion'] ?? '',
                'placa_rodaje' => $datosExternos['placa_rodaje'] ?? '',
                'anio_fabricacion' => $datosExternos['anio_fabricacion'] ?? '',
                'marca' => $datosExternos['marca'] ?? '',
                'numero_motor' => $datosExternos['numero_motor'] ?? '',
                'tipo_motor' => $datosExternos['tipo_motor'] ?? '',
                'serie' => $datosExternos['serie'] ?? '',
                'color' => $datosExternos['color'] ?? '',
                'fecha_registro' => $datosExternos['fecha_registro'] ?? '',
                'id_empresa' => $datosExternos['id_empresa'] ?? null,
                'empresa' => [
                    'razon_social' => $datosExternos['empresa']['razon_social'] ?? ($datosExternos['razon_social'] ?? ''),
                    'ruc' => $datosExternos['empresa']['ruc'] ?? ($datosExternos['ruc'] ?? ''),
                    'representante_legal' => $datosExternos['empresa']['representante_legal'] ?? ($datosExternos['representante_legal'] ?? '')
                ],
                'estado_registro' => $datosExternos['estado_registro'] ?? 'ACTIVO',
                'fecha_actualizacion' => date('Y-m-d H:i:s'),
                'fuente' => 'API_EXTERNA'
            ];
        }
        
        return [
            'id' => $datosExternos['id'] ?? null,
            'numero_asignado' => $datosExternos['numero'] ?? $datosExternos['numero_asignado'] ?? '',
            'nombre_completo' => $datosExternos['nombre'] ?? $datosExternos['nombre_completo'] ?? '',
            'dni' => $datosExternos['dni'] ?? '',
            'direccion' => $datosExternos['direccion'] ?? $datosExternos['dirreccion'] ?? '',
            'placa_rodaje' => $datosExternos['placa'] ?? $datosExternos['placa_rodaje'] ?? '',
            'anio_fabricacion' => $datosExternos['anio'] ?? $datosExternos['anio_fabricacion'] ?? '',
            'marca' => $datosExternos['marca'] ?? '',
            'numero_motor' => $datosExternos['motor'] ?? $datosExternos['numero_motor'] ?? '',
            'tipo_motor' => $datosExternos['tipo_motor'] ?? '',
            'serie' => $datosExternos['serie'] ?? '',
            'color' => $datosExternos['color'] ?? '',
            'fecha_registro' => $datosExternos['fecha'] ?? $datosExternos['fecha_registro'] ?? '',
            'id_empresa' => $datosExternos['id_empresa'] ?? null,
            'empresa' => [
                'razon_social' => $datosExternos['empresa'] ?? $datosExternos['razon_social'] ?? '',
                'ruc' => $datosExternos['ruc_empresa'] ?? $datosExternos['ruc'] ?? '',
                'representante_legal' => $datosExternos['representante'] ?? $datosExternos['representante_legal'] ?? ''
            ],
            'estado_registro' => $datosExternos['estado'] ?? $datosExternos['estado_registro'] ?? 'ACTIVO',
            'fecha_actualizacion' => date('Y-m-d H:i:s'),
            'fuente' => 'API_EXTERNA'
        ];
    }

    public function verificarDisponibilidadAPI() {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->api_endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $http_code === 200;
        } catch (Exception $e) {
            error_log("Error verificando disponibilidad API: " . $e->getMessage());
            return false;
        }
    }

    public function probarConexionAPI() {
        try {
            $datos = $this->obtenerDatosDirectosAPI();
            if ($datos) {
                return [
                    'conexion_exitosa' => true,
                    'total_registros' => isset($datos['data']) ? count($datos['data']) : 0,
                    'datos_muestra' => isset($datos['data'][0]) ? $datos['data'][0] : null
                ];
            }
            return ['conexion_exitosa' => false];
        } catch (Exception $e) {
            error_log("Error probando conexión API: " . $e->getMessage());
            return ['conexion_exitosa' => false, 'error' => $e->getMessage()];
        }
    }
}
?>