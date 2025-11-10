<?php
namespace Controllers;

use Model\Cita;
use PDO;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

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
                    e.nombre_estado_cita,
                    v.placa,
                    v.marca,
                    v.modelo,
                    v.carroceria,
                    u.nombre AS nombre_cliente,
                    u.apellido AS apellido_cliente,
                    u.email AS email_cliente
                FROM cita c
                INNER JOIN vehiculo v ON c.id_vehiculo = v.id
                INNER JOIN usuario u ON v.id_usuario = u.id
                INNER JOIN estado_cita e ON c.id_estado_cita = e.id
                ORDER BY c.fecha DESC, c.hora DESC
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
                error_log("📧 Llamando a enviarAvisoCita...");
                // Enviar correo (no afecta si falla)
                self::enviarAvisoCita($input['id_vehiculo'], $input['fecha'], $input['hora']);

                error_log("📧 Envío ejecutado");
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

    // ===========================================
    // 📧 Envío de correo de notificación de cita
    // ===========================================
    private static function enviarAvisoCita($idVehiculo, $fecha, $hora)
    {
        error_log("🚀 [enviarAvisoCita] Iniciando envío de correo...");
        error_log("🧾 ID Vehículo: $idVehiculo, Fecha: $fecha, Hora: $hora");
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../includes/config/database.php';

        $db = conectarDB();

        // 🔎 Obtener datos del vehículo y su propietario
        $stmt = $db->prepare("
            SELECT 
                v.placa, v.marca, v.modelo, v.carroceria,
                u.nombre, u.apellido, u.email
            FROM vehiculo v
            JOIN usuario u ON v.id_usuario = u.id
            WHERE v.id = :idVehiculo
        ");
        $stmt->execute(['idVehiculo' => $idVehiculo]);
        $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehiculo) {
            error_log("❌ No se encontró el vehículo con ID $idVehiculo para enviar correo");
            return ['ok' => false, 'error' => 'Vehículo no encontrado'];
        }
        error_log("✅ Vehículo encontrado: " . json_encode($vehiculo));

        // 🧩 Configurar PHPMailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Cargar .env
            cargarEnv(dirname(__DIR__, 2) . '/.env');

            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USERNAME');
            $mail->Password = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)getenv('MAIL_PORT') ?: 2525;

            // 📬 Configurar envío
            $mail->setFrom('no-reply@tecnocitascda.com', 'TecnoCitasCDA');
            $mail->addAddress('TecnoCitas@mailtrap.io', 'TecnoCitasCDA');// Mailtrap
            $mail->isHTML(true);
            $mail->Subject = 'Nueva cita agendada - Revisión Técnico-Mecánica';

            // ✉️ Contenido del mensaje
            $nombreCliente = $vehiculo['nombre'] . ' ' . $vehiculo['apellido'];
            $emailCliente  = $vehiculo['email'];
            $placa         = $vehiculo['placa'];
            $marca         = $vehiculo['marca'];
            $modelo        = $vehiculo['modelo'];
            $carroceria    = $vehiculo['carroceria'] ?: 'N/A';

            $mail->Body = "
                <h2>📅 Nueva cita de revisión técnico-mecánica</h2>

                <h3>Información del cliente</h3>
                <table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>
                <tr>
                    <td><strong>Nombre</strong></td>
                    <td>{$vehiculo['nombre']} {$vehiculo['apellido']}</td>
                </tr>
                <tr>
                    <td><strong>Correo</strong></td>
                    <td>{$vehiculo['email']}</td>
                </tr>
                </table>

                <h3>Datos del vehículo</h3>
                <table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>
                <tr>
                    <td><strong>Placa</strong></td>
                    <td>{$vehiculo['placa']}</td>
                </tr>
                <tr>
                    <td><strong>Marca</strong></td>
                    <td>{$vehiculo['marca']}</td>
                </tr>
                <tr>
                    <td><strong>Modelo</strong></td>
                    <td>{$vehiculo['modelo']}</td>
                </tr>
                <tr>
                    <td><strong>Carrocería</strong></td>
                    <td>{$vehiculo['carroceria']}</td>
                </tr>
                </table>

                <h3>Detalles de la cita</h3>
                <table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>
                <tr>
                    <td><strong>Fecha</strong></td>
                    <td>{$fecha}</td>
                </tr>
                <tr>
                    <td><strong>Hora</strong></td>
                    <td>{$hora}</td>
                </tr>
                </table>

                <p>Este correo fue generado automáticamente por <strong>TecnoCitasCDA</strong>.</p>
                ";


            $mail->AltBody = "
                Nueva cita de revisión técnico-mecánica
                ---------------------------------------
                Cliente: {$vehiculo['nombre']} {$vehiculo['apellido']}
                Correo: {$vehiculo['email']}

                Vehículo:
                - Placa: {$vehiculo['placa']}
                - Marca: {$vehiculo['marca']}
                - Modelo: {$vehiculo['modelo']}
                - Carrocería: {$vehiculo['carroceria']}

                Fecha: {$fecha}
                Hora: {$hora}

                TecnoCitasCDA
                ";

            error_log("📬 Enviando correo con PHPMailer...");


            $mail->send();
            error_log("✅ Correo de cita enviado correctamente para $placa ($emailCliente)");
            return ['ok' => true];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("❌ Error enviando correo: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }


    // PUT /api/citas/estado
    public static function cambiarEstado()
    {
        verificarRolesPermitidosPorID([1,3]); // admin y técnico

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

            // Verificar que exista la cita
            $check = $db->prepare("SELECT id FROM cita WHERE id = :id");
            $check->execute([':id' => (int)$id]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Cita no encontrada']);
                exit;
            }

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

    public static function obtenerCitaPorId($id)
    {
        verificarRolesPermitidosPorID([1,3]); // admin y técnico

        try {
            $db = conectarDB();
            $query = "
                SELECT 
                    c.id,
                    c.fecha,
                    c.hora,
                    c.id_estado_cita,
                    e.nombre_estado_cita,
                    v.id AS id_vehiculo,
                    v.placa,
                    v.marca,
                    v.modelo,
                    v.carroceria,
                    v.imagen,
                    u.nombre AS nombre_cliente,
                    u.apellido AS apellido_cliente,
                    u.email AS email_cliente
                FROM cita c
                INNER JOIN vehiculo v ON c.id_vehiculo = v.id
                INNER JOIN usuario u ON v.id_usuario = u.id
                INNER JOIN estado_cita e ON c.id_estado_cita = e.id
                WHERE c.id = :id
                LIMIT 1
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => (int)$id]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cita) {
                echo json_encode(['ok' => true, 'cita' => $cita]);
            } else {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Cita no encontrada']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al obtener cita', 'error' => $e->getMessage()]);
        }
    }

}
