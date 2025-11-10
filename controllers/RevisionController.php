<?php
namespace Controllers;

use Model\Revision;
use Model\DetalleInspeccion;
use Model\DetalleParametroInspeccion;

require_once __DIR__ . '/../includes/config/database.php';

class RevisionController
{
    // POST /api/revision/crear
    public static function crearRevision()
    {
        verificarRolesPermitidosPorID([1, 3]); // admin / técnico

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id_cita']) || empty($input['resultado'])) {
            echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $db = conectarDB();

        try {
            // 1️⃣ Guardar detalle de inspección
            $detalle = new DetalleInspeccion([
                'resultado' => $input['resultado'],
                'observaciones' => $input['observaciones'] ?? '',
                'recomendaciones' => $input['recomendaciones'] ?? '',
                'efectividad_numero' => $input['efectividad_numero'] ?? 0
            ]);

            if (!$detalle->guardar()) {
                echo json_encode(['ok' => false, 'message' => 'Error al crear detalle de inspección']);
                return;
            }

            // 2️⃣ Crear la revisión
            $revision = new Revision([
                'id_cita' => (int)$input['id_cita'],
                'id_detalle_inspeccion' => $detalle->id,
                'fecha_inspeccion' => date('Y-m-d')
            ]);
            $revision->guardar();

            // 3️⃣ Guardar parámetros si vienen
            if (!empty($input['parametros'])) {
                foreach ($input['parametros'] as $p) {
                    $param = new DetalleParametroInspeccion([
                        'id_parametro_inspeccion' => $p['id_parametro'],
                        'id_detalle_inspeccion' => $detalle->id,
                        'valor_medicion' => $p['valor_medicion'] ?? '',
                        'resultado_parametro' => $p['resultado_parametro'] ?? ''
                    ]);
                    $param->guardar();
                }
            }

            echo json_encode(['ok' => true, 'message' => 'Revisión registrada correctamente']);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'message' => 'Error al guardar revisión', 'error' => $e->getMessage()]);
        }
    }

    // GET /api/revision/ver?id=#
    public static function verRevision()
    {
        verificarRolesPermitidosPorID([1,3]); // admin / técnico

        $idCita = $_GET['id_cita'] ?? null;
        $idRevision = $_GET['id_revision'] ?? null;

        if (!$idCita && !$idRevision) {
            echo json_encode(['ok' => false, 'message' => 'Debe enviar id_cita o id_revision']);
            return;
        }

        $db = conectarDB();

        // 🔹 Usa la condición correcta según el parámetro recibido
        $where = $idRevision ? "r.id = :id" : "r.id_cita = :id";

        $query = "
            SELECT 
                r.id AS id_revision, 
                r.fecha_inspeccion, 
                d.resultado, 
                d.observaciones, 
                d.recomendaciones, 
                d.efectividad_numero,
                c.id AS id_cita, 
                v.placa, 
                v.marca, 
                v.modelo, 
                v.carroceria,
                u.nombre, 
                u.apellido, 
                u.email
            FROM revision r
            JOIN detalle_inspeccion d ON r.id_detalle_inspeccion = d.id
            JOIN cita c ON r.id_cita = c.id
            JOIN vehiculo v ON c.id_vehiculo = v.id
            JOIN usuario u ON v.id_usuario = u.id
            WHERE $where
            LIMIT 1
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $idRevision ?? $idCita]);
        $revision = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($revision) {
            echo json_encode(['ok' => true, 'revision' => $revision]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Revisión no encontrada']);
        }
    }



    // GET /api/revision/listar
    public static function listarRevisiones()
    {
        verificarRolesPermitidosPorID([1,3]); // admin y técnico
        try {
            $db = conectarDB();

            $query = "
                SELECT 
                    r.id AS id_revision,
                    r.fecha_inspeccion,
                    d.resultado,
                    d.efectividad_numero,
                    v.placa,
                    v.marca,
                    v.modelo,
                    u.nombre AS nombre_cliente,
                    u.apellido AS apellido_cliente,
                    e.nombre_estado_cita AS estado_cita
                FROM revision r
                JOIN detalle_inspeccion d ON r.id_detalle_inspeccion = d.id
                JOIN cita c ON r.id_cita = c.id
                JOIN vehiculo v ON c.id_vehiculo = v.id
                JOIN usuario u ON v.id_usuario = u.id
                JOIN estado_cita e ON c.id_estado_cita = e.id
                ORDER BY r.fecha_inspeccion DESC
            ";

            $stmt = $db->query($query);
            $revisiones = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'revisiones' => $revisiones]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al listar revisiones', 'error' => $e->getMessage()]);
        }
    }

}
