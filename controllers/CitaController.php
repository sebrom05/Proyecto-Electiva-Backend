<?php
namespace Controllers;

use Model\Cita;
use PDO;
use Exception;

class CitaController {

    // GET /api/citas/estados
    public static function listarEstados()
    {
        verificarRolesPermitidosPorID([1,2,3]); // admin, cliente, técnico
        try {
            $db = conectarDB();
            $stmt = $db->query("SELECT id, nombre_estado_cita FROM estado_cita ORDER BY id ASC");
            $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'estados' => $estados]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al listar estados', 'error' => $e->getMessage()]);
        }
    }

    // GET /api/citas/listar
    public static function listarCitas()
    {
        verificarRolesPermitidosPorID([1,3]); // admin y técnico
        try {
            $db = conectarDB();
            $query = "
                SELECT 
                    c.id,
                    c.fecha,
                    c.hora,
                    c.fecha_registro_cita,
                    e.nombre_estado_cita AS estado,
                    v.id AS id_vehiculo,
                    v.placa,
                    v.marca,
                    v.modelo,
                    CONCAT(u.nombre, ' ', u.apellido) AS propietario
                FROM cita c
                JOIN estado_cita e ON c.id_estado_cita = e.id
                JOIN vehiculo v ON c.id_vehiculo = v.id
                JOIN usuario u ON v.id_usuario = u.id
                ORDER BY c.fecha DESC, c.hora ASC
            ";
            $stmt = $db->query($query);
            $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'citas' => $citas]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al listar citas', 'error' => $e->getMessage()]);
        }
    }

    // POST /api/citas/crear
    public static function crearCita()
    {
        $usuario = verificarSesionAPI();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id_vehiculo']) || empty($input['fecha']) || empty($input['hora'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Faltan datos requeridos']);
            exit;
        }

        try {
            $cita = new Cita([
                'id_vehiculo' => (int)$input['id_vehiculo'],
                'id_estado_cita' => 1, // Pendiente
                'fecha' => $input['fecha'],
                'hora'  => $input['hora']
            ]);

            if ($cita->guardar()) {
                echo json_encode(['ok' => true, 'message' => 'Cita registrada correctamente', 'id' => $cita->id]);
            } else {
                http_response_code(500);
                echo json_encode(['ok' => false, 'message' => 'Error al registrar cita']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error en el servidor', 'error' => $e->getMessage()]);
        }
    }

    // PUT /api/citas/estado
    public static function cambiarEstado()
    {
        verificarRolesPermitidosPorID([1,3]); // admin y técnico

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $nuevoEstado = $input['id_estado_cita'] ?? null;

        if (!$id || !$nuevoEstado) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        try {
            $db = conectarDB();
            $stmt = $db->prepare("UPDATE cita SET id_estado_cita = :estado WHERE id = :id");
            $stmt->execute([':estado' => (int)$nuevoEstado, ':id' => (int)$id]);
            echo json_encode(['ok' => true, 'message' => 'Estado de cita actualizado correctamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al actualizar estado', 'error' => $e->getMessage()]);
        }
    }

    // (Opcional) GET /api/citas/mis-citas  → para rol cliente (listar solo sus vehículos)
    public static function listarCitasPorUsuario()
    {
        verificarRolesPermitidosPorID([2]); // cliente
        session_start();
        $idUsuario = $_SESSION['usuario']['id'] ?? null;
        if (!$idUsuario) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'No autenticado']);
            exit;
        }
        try {
            $db = conectarDB();
            $query = "
                SELECT c.id, c.fecha, c.hora, e.nombre_estado_cita AS estado, v.placa, v.marca, v.modelo
                FROM cita c
                JOIN estado_cita e ON c.id_estado_cita = e.id
                JOIN vehiculo v ON c.id_vehiculo = v.id
                WHERE v.id_usuario = :idUsuario
                ORDER BY c.fecha DESC, c.hora ASC
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([':idUsuario' => $idUsuario]);
            echo json_encode(['ok' => true, 'citas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al listar', 'error' => $e->getMessage()]);
        }
    }
}
