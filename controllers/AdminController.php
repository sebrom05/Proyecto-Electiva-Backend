<?php
namespace Controllers;

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/cors.php';

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
        verificarAdmin(); // ✅ Misma validación

        try {
            $db = conectarDB();

            $stmt = $db->prepare("
                SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.documento, r.nombre AS rol
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
}
