<?php
namespace Controllers;

use PDO;
use Exception;

require_once __DIR__ . '/../includes/cors.php';
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
                cu.visible,
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

    public static function cambiarVisibilidad()
    {
        verificarRolesPermitidosPorID([1]); // solo admin

        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input["id"] ?? null;
        $visible = $input["visible"] ?? null;

        if (!$id || !is_bool($visible)) {
            echo json_encode(['ok' => false, 'message' => 'Datos inválidos']);
            return;
        }

        $db = conectarDB();

        $stmt = $db->prepare("UPDATE calificacion_usuario SET visible = :vis WHERE id = :id");
        $stmt->execute([
            ":vis" => $visible,
            ":id" => $id
        ]);

        echo json_encode(['ok' => true, 'message' => 'Visibilidad actualizada']);
    }

    public static function crear()
    {
        $usuario = verificarSesionAPI(); // solo clientes logueados

        $input = json_decode(file_get_contents("php://input"), true);
        $puntaje = intval($input["puntaje"] ?? 0);
        $mensaje = trim($input["mensaje"] ?? "");

        if ($puntaje < 1 || $puntaje > 5 || !$mensaje) {
            echo json_encode(['ok' => false, 'message' => 'Datos inválidos']);
            return;
        }

        $db = conectarDB();

        $stmt = $db->prepare("
            INSERT INTO calificacion_usuario (id_usuario, puntaje, mensaje, visible)
            VALUES (:u, :p, :m, true)
        ");

        $stmt->execute([
            ":u" => $usuario["id"],
            ":p" => $puntaje,
            ":m" => $mensaje
        ]);

        echo json_encode(["ok" => true]);
    }

    public static function listarPublicos()
    {
        $db = conectarDB();

        $query = "
            SELECT 
                c.id,
                c.puntaje,
                c.mensaje,
                c.fecha_calificacion,
                u.nombre,
                u.apellido
            FROM calificacion_usuario c
            JOIN usuario u ON c.id_usuario = u.id
            WHERE c.visible = true
            ORDER BY c.fecha_calificacion DESC
            LIMIT 20
        ";

        $stmt = $db->query($query);
        $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "ok" => true,
            "comentarios" => $comentarios
        ]);
    }

    public static function actualizarVisibilidad() {
        verificarRolesPermitidosPorID([1]); // SOLO ADMIN

        error_log("🔄 [actualizarVisibilidad] Entrando al método...");

        $inputRaw = file_get_contents("php://input");
        error_log("📥 RAW input: " . $inputRaw);

        $input = json_decode($inputRaw, true);

        $id = $input['id'] ?? null;
        $visible = $input['visible'] ?? null;

        error_log("📌 ID recibido: " . var_export($id, true));
        error_log("📌 Visible recibido: " . var_export($visible, true));

        if (!$id || $visible === null) {
            error_log("❌ Datos incompletos en actualizarVisibilidad");
            echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
            return;
        }

        try {
            $db = conectarDB();

            // Asegúrate que la columna visible exista:
            // ALTER TABLE calificacion_usuario ADD COLUMN visible BOOLEAN DEFAULT true;

            $stmt = $db->prepare("UPDATE calificacion_usuario SET visible = :visible WHERE id = :id");
            $stmt->execute([
                ':visible' => $visible ? 1 : 0,
                ':id' => (int)$id
            ]);

            error_log("✅ Visibilidad actualizada para ID={$id} -> visible=" . ($visible ? '1' : '0'));

            echo json_encode(['ok' => true, 'message' => 'Visibilidad actualizada']);
        } catch (\Exception $e) {
            error_log("❌ Error en actualizarVisibilidad: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error en el servidor', 'error' => $e->getMessage()]);
        }
    }




}
