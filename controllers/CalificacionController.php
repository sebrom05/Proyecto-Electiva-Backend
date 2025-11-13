<?php
namespace Controllers;

use PDO;
use Exception;

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/funciones.php';

class CalificacionController
{
    // ... aquí van los métodos que ya tienes ...

    // 🔹 GET /api/calificaciones/estadisticas  (solo admin / técnico)
    public static function estadisticasAdmin()
    {
        require_once __DIR__ . '/../includes/cors.php';

        // ✅ Solo admin (1) y técnico (3)
        verificarRolesPermitidosPorID([1, 3]);

        try {
            $db = conectarDB();

            // 👉 Resumen general: total y promedio
            $sqlResumen = "
                SELECT 
                    COUNT(*) AS total,
                    COALESCE(ROUND(AVG(puntaje)::numeric, 2), 0) AS promedio
                FROM calificacion_usuario
            ";
            $stmt = $db->query($sqlResumen);
            $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = (int)($resumen['total'] ?? 0);
            $promedio = (float)($resumen['promedio'] ?? 0);

            // 👉 Distribución por estrellas
            $sqlEstrellas = "
                SELECT puntaje, COUNT(*) AS cantidad
                FROM calificacion_usuario
                GROUP BY puntaje
                ORDER BY puntaje
            ";
            $stmt2 = $db->query($sqlEstrellas);
            $rowsEstrellas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Arreglo fijo 0..5 para que el front no se rompa si falta alguna estrella
            $porEstrellas = [];
            for ($i = 0; $i <= 5; $i++) {
                $porEstrellas[$i] = 0;
            }

            foreach ($rowsEstrellas as $row) {
                $p = (int)$row['puntaje'];
                $porEstrellas[$p] = (int)$row['cantidad'];
            }

            // 👉 Últimos comentarios (ej: últimos 5)
            $sqlUltimos = "
                SELECT 
                    cu.id,
                    cu.puntaje,
                    cu.mensaje,
                    cu.fecha_calificacion,
                    u.nombre,
                    u.apellido
                FROM calificacion_usuario cu
                JOIN usuario u ON cu.id_usuario = u.id
                ORDER BY cu.fecha_calificacion DESC
                LIMIT 5
            ";
            $stmt3 = $db->query($sqlUltimos);
            $ultimos = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'resumen' => [
                    'total'    => $total,
                    'promedio' => $promedio
                ],
                'por_estrellas' => $porEstrellas,
                'ultimos'       => $ultimos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al obtener estadísticas de calificaciones',
                'error' => $e->getMessage()
            ]);
        }
    }
}
