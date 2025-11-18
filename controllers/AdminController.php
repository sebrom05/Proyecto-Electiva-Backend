<?php
namespace Controllers;

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/app.php';


use PDO;
use Exception;

class AdminController
{
    // ======================================
    // 🔹 Obtener estadísticas del sistema
    // ======================================
    public static function obtenerEstadisticas()
    {
        verificarAdmin(); // ✅ Usa la función global

        try {
            $db = conectarDB();

            $consultas = [
                'usuarios'   => "SELECT COUNT(*) AS total FROM usuario",
                'vehiculos'  => "SELECT COUNT(*) AS total FROM vehiculo",
                'revisiones' => "SELECT COUNT(*) AS total FROM revision",
                'pendientes' => "SELECT COUNT(*) AS total
                                FROM cita c
                                INNER JOIN estado_cita e ON c.id_estado_cita = e.id
                                WHERE LOWER(e.nombre_estado_cita) = 'pendiente'",
                'aprobadas'  => "SELECT COUNT(*) AS total
                                FROM cita c
                                INNER JOIN estado_cita e ON c.id_estado_cita = e.id
                                WHERE LOWER(e.nombre_estado_cita) = 'aprobada'"
            ];


            $resultados = [];
            foreach ($consultas as $key => $sql) {
                $stmt = $db->query($sql);
                $resultados[$key] = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            }

            echo json_encode([
                'ok' => true,
                'data' => [
                    'totalUsuarios' => $resultados['usuarios'],
                    'totalVehiculos' => $resultados['vehiculos'],
                    'totalRevisiones' => $resultados['revisiones'],
                    'revisionesPendientes' => $resultados['pendientes'],
                    'revisionesAprobadas' => $resultados['aprobadas'],
                ],
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al obtener estadísticas.',
                'error' => $e->getMessage()
            ]);
        }
    }

    // ======================================
    // 🔹 Listar usuarios del sistema
    // ======================================
    public static function listarUsuarios()
    {
        verificarAdmin();

        try {
            $db = conectarDB();

            $stmt = $db->prepare("
                SELECT 
                    u.id, 
                    u.nombre, 
                    u.apellido, 
                    u.email, 
                    u.telefono, 
                    u.documento, 
                    r.nombre_rol AS rol,
                    CASE 
                        WHEN u.activo = TRUE THEN 'Activo'
                        ELSE 'Inactivo'
                    END AS estado
                FROM usuario u
                LEFT JOIN rol_usuario r ON u.id_rol_usuario = r.id
                ORDER BY u.id DESC
            ");
            $stmt->execute();

            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'usuarios' => $usuarios]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al listar usuarios.',
                'error' => $e->getMessage()
            ]);
        }
    }


    // ======================================
    // 🔹 Eliminar usuario por ID
    // ======================================
    public static function eliminarUsuario()
    {
        verificarAdmin(); // ✅ Solo el admin puede eliminar

        // Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        // Leer el ID desde la URL o el cuerpo JSON
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? null;

        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID de usuario no válido']);
            exit;
        }

        try {
            $db = conectarDB();

            // Verificar que el usuario exista
            $stmt = $db->prepare("SELECT id FROM usuario WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $existe = $stmt->fetchColumn();

            if (!$existe) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Usuario no encontrado']);
                exit;
            }

            // 🔹 En lugar de eliminar, se marca como inactivo
            $update = $db->prepare("UPDATE usuario SET activo = FALSE WHERE id = :id");
            $update->execute(['id' => $id]);

            echo json_encode([
                'ok' => true,
                'message' => "Usuario con ID $id marcado como inactivo correctamente"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function activarUsuario()
    {
        verificarAdmin(); // ✅ Solo el admin puede activar

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? null;

        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID de usuario no válido']);
            exit;
        }

        try {
            $db = conectarDB();

            // Verificar que exista y esté inactivo
            $stmt = $db->prepare("SELECT id, activo FROM usuario WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Usuario no encontrado']);
                exit;
            }

            if ($usuario['activo'] == true) {
                echo json_encode(['ok' => false, 'message' => 'El usuario ya estaba activo.']);
                exit;
            }

            // 🔹 Activar usuario
            $update = $db->prepare("UPDATE usuario SET activo = TRUE WHERE id = :id");
            $update->execute(['id' => $id]);

            echo json_encode([
                'ok' => true,
                'message' => "Usuario con ID $id activado correctamente"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al activar usuario',
                'error' => $e->getMessage()
            ]);
        }
    }


}
