<?php
namespace Controllers;

use Model\Revision;
use Model\DetalleInspeccion;
use Model\DetalleParametroInspeccion;

use PDO;
use Exception;

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
            // 🔹 Iniciamos transacción
            $db->beginTransaction();

            // 1️⃣ Guardar detalle de inspección
            $detalle = new DetalleInspeccion([
                'resultado' => $input['resultado'],
                'observaciones' => $input['observaciones'] ?? '',
                'recomendaciones' => $input['recomendaciones'] ?? '',
                'efectividad_numero' => $input['efectividad_numero'] ?? 0
            ]);

            if (!$detalle->guardar()) {
                $db->rollBack();
                echo json_encode(['ok' => false, 'message' => 'Error al crear detalle de inspección']);
                return;
            }

            // 2️⃣ Crear la revisión
            $revision = new Revision([
                'id_cita' => (int)$input['id_cita'],
                'id_detalle_inspeccion' => $detalle->id,
                'fecha_inspeccion' => date('Y-m-d')
            ]);

            if (!$revision->guardar()) {
                $db->rollBack();
                echo json_encode(['ok' => false, 'message' => 'Error al crear revisión']);
                return;
            }

            // 3️⃣ Guardar parámetros si vienen
            if (!empty($input['parametros'])) {
                foreach ($input['parametros'] as $p) {
                    $param = new DetalleParametroInspeccion([
                        'id_parametro_inspeccion' => $p['id_parametro'],
                        'id_detalle_inspeccion' => $detalle->id,
                        'valor_medicion' => $p['valor_medicion'] ?? '',
                        'resultado_parametro' => $p['resultado_parametro'] ?? ''
                    ]);
                    if (!$param->guardar()) {
                        $db->rollBack();
                        echo json_encode(['ok' => false, 'message' => 'Error al guardar parámetros']);
                        return;
                    }
                }
            }

            // 4️⃣ Cambiar estado de la cita a "Finalizada"
            $sqlEstado = "
                UPDATE cita 
                SET id_estado_cita = (
                    SELECT id FROM estado_cita 
                    WHERE LOWER(nombre_estado_cita) = 'finalizada'
                    LIMIT 1
                )
                WHERE id = :id_cita
            ";
            $stmtEstado = $db->prepare($sqlEstado);
            $stmtEstado->execute([':id_cita' => (int)$input['id_cita']]);

            // (Opcional: si quieres asegurarte de que sí cambió una fila)
            if ($stmtEstado->rowCount() === 0) {
                // Si no encontró el estado o cita, puedes decidir si haces rollback o no
                // Por ahora solo dejamos un log:
                error_log("⚠ No se pudo actualizar estado de cita a Finalizada para id_cita={$input['id_cita']}");
            }

            // ✅ Todo bien → confirmamos
            $db->commit();

            echo json_encode(['ok' => true, 'message' => 'Revisión registrada y cita finalizada correctamente']);
        } catch (\Exception $e) {
            $db->rollBack();
            echo json_encode([
                'ok' => false,
                'message' => 'Error al guardar revisión',
                'error' => $e->getMessage()
            ]);
        }

        // Buscar datos del cliente
        $sql = "
            SELECT u.nombre, u.apellido, u.email
            FROM cita c
            JOIN vehiculo v ON c.id_vehiculo = v.id
            JOIN usuario u ON v.id_usuario = u.id
            WHERE c.id = :id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => (int)$input['id_cita']]);
        $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($cliente) {
            $nombre = $cliente['nombre'] . ' ' . $cliente['apellido'];
            $email = $cliente['email'];

            $asunto = "📋 Resultado de tu revisión técnico-mecánica";

            $html = "
                <h2>Resultado de tu revisión técnico-mecánica</h2>
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Tu revisión para la cita #{$input['id_cita']} ha sido finalizada.</p>
                <p><strong>Resultado general:</strong> {$input['resultado']}</p>
                <p><strong>Observaciones:</strong> {$input['observaciones']}</p>
                <p><strong>Recomendaciones:</strong> {$input['recomendaciones']}</p>
                <p>Gracias por confiar en TecnoCitasCDA.</p>
            ";

            self::enviarCorreoRevision($email, $nombre, $asunto, $html);
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

        if (!$revision) {
            echo json_encode(['ok' => false, 'message' => 'Revisión no encontrada']);
            return;
        }

        // 🔹 Obtener los parámetros evaluados
        $parametrosQuery = "
            SELECT 
                p.nombre_parametro,
                p.categoria,
                dp.valor_medicion,
                dp.resultado_parametro
            FROM detalle_parametro_inspeccion dp
            JOIN parametro_inspeccion p ON dp.id_parametro_inspeccion = p.id
            JOIN detalle_inspeccion di ON dp.id_detalle_inspeccion = di.id
            JOIN revision r ON r.id_detalle_inspeccion = di.id
            WHERE r.id = :idRevision
        ";

        $stmt2 = $db->prepare($parametrosQuery);
        $stmt2->execute([':idRevision' => $revision['id_revision']]);
        $parametros = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        $revision['parametros'] = $parametros;

        echo json_encode(['ok' => true, 'revision' => $revision]);
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


    public static function listarParametros()
    {
        verificarRolesPermitidosPorID([1, 3]); // admin y técnico

        try {
            $db = conectarDB();

            $query = "
                SELECT 
                    id,
                    nombre_parametro,
                    descripcion,
                    categoria,
                    estado
                FROM parametro_inspeccion
                ORDER BY categoria ASC, nombre_parametro ASC
            ";

            $stmt = $db->query($query);
            $parametros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'parametros' => $parametros]);

        } catch (\Exception $e) {
            echo json_encode([
                'ok' => false,
                'message' => 'Error al listar parámetros',
                'error' => $e->getMessage()
            ]);
        }
    }

    private static function enviarCorreoRevision($email, $nombre, $asunto, $html)
    {
        error_log("📨 [enviarCorreoRevision] Preparando correo a $email");

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Cargar variables de entorno
            cargarEnv(dirname(__DIR__, 2) . '/.env');

            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USERNAME');
            $mail->Password = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)getenv('MAIL_PORT') ?: 2525;

            // Configurar correo
            $mail->setFrom('no-reply@tecnocitascda.com', 'TecnoCitasCDA');
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $html;

            $mail->send();

            error_log("✅ [enviarCorreoRevision] Correo enviado correctamente");
            return true;

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("❌ Error al enviar correo: " . $mail->ErrorInfo);
            return false;
        }
    }

    public static function misRevisiones() {
        $usuario = verificarSesionAPI(); // solo clientes logueados

        $db = conectarDB();

        $query = "
            SELECT 
                r.id AS id_revision,
                r.fecha_inspeccion,
                v.placa,
                v.marca,
                v.modelo,
                d.resultado,
                d.efectividad_numero,
                CASE 
                    WHEN d.resultado >= 3 THEN 'APROBADA'
                    ELSE 'RECHAZADA'
                END AS estado_revision
            FROM revision r
            JOIN detalle_inspeccion d ON r.id_detalle_inspeccion = d.id
            JOIN cita c ON r.id_cita = c.id
            JOIN vehiculo v ON c.id_vehiculo = v.id
            WHERE v.id_usuario = :id
            ORDER BY r.fecha_inspeccion DESC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $usuario['id']]);

        $revisiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'revisiones' => $revisiones]);
    }

    public static function verRevisionCliente()
    {
        $usuario = verificarSesionAPI(); // cliente autenticadooo
        if (!$usuario) {
            echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
            return;
        }

        $idRevision = $_GET['id_revision'] ?? null;

        if (!$idRevision) {
            echo json_encode(['ok' => false, 'message' => 'ID de revisión requerido']);
            return;
        }

        $db = conectarDB();

        // 🔍 Validar que la revisión pertenece al cliente
        $query = "
            SELECT 
                r.id AS id_revision,
                r.fecha_inspeccion,
                r.id_detalle_inspeccion,
                d.resultado,
                d.observaciones,
                d.recomendaciones,
                d.efectividad_numero,
                v.placa,
                v.marca,
                v.modelo,
                u.nombre,
                u.apellido
            FROM revision r
            JOIN detalle_inspeccion d ON r.id_detalle_inspeccion = d.id
            JOIN cita c ON r.id_cita = c.id
            JOIN vehiculo v ON c.id_vehiculo = v.id
            JOIN usuario u ON v.id_usuario = u.id
            WHERE r.id = :idRevision AND v.id_usuario = :idUsuario
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':idRevision' => $idRevision,
            ':idUsuario' => $usuario['id']
        ]);

        $revision = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$revision) {
            echo json_encode(['ok' => false, 'message' => 'Esta revisión no pertenece al usuario']);
            return;
        }

        // 🔍 Traer parámetros evaluados
        $parametrosQuery = "
            SELECT 
                p.nombre_parametro,
                p.categoria,
                dp.valor_medicion,
                dp.resultado_parametro
            FROM detalle_parametro_inspeccion dp
            JOIN parametro_inspeccion p ON dp.id_parametro_inspeccion = p.id
            JOIN detalle_inspeccion di ON dp.id_detalle_inspeccion = di.id
            WHERE di.id = :idDetalle
        ";

        $stmt2 = $db->prepare($parametrosQuery);
        $stmt2->execute([':idDetalle' => $revision['id_detalle_inspeccion']]);
        $parametros = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Adjuntar parámetros al resultado
        $revision['parametros'] = $parametros;

        echo json_encode(['ok' => true, 'revision' => $revision]);
    }




}
