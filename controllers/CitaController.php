<?php
namespace Controllers;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/cors.php';

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
        $db = conectarDB();


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
        /*
        --------------------------------------------------------------------
        🔹 VALIDACIÓN DE INTERVALOS DE MEDIA HORA (AQUÍ VA EL CÓDIGO)
        --------------------------------------------------------------------
        */

        // Validar que la hora esté en intervalos de 30 minutos
        $hora = $input['hora'];
        $partes = explode(':', $hora);

        if (count($partes) !== 2) {
            echo json_encode(['ok' => false, 'message' => 'Formato de hora inválido']);
            exit;
        }

        $minutos = (int)$partes[1];

        if ($minutos !== 0 && $minutos !== 30) {
            echo json_encode([
                'ok' => false,
                'message' => 'Solo se permiten citas cada 30 minutos (00 o 30 minutos).'
            ]);
            exit;
        }
        // 1. Comprobar si ya existe una cita en CONFIRMADA en la misma fecha y hora
        $check = $db->prepare("
            SELECT id FROM cita 
            WHERE fecha = :fecha 
            AND hora = :hora 
            AND id_estado_cita = 3 -- 3 = Confirmada
            LIMIT 1
        ");
        $check->execute([
            ':fecha' => $input['fecha'],
            ':hora'  => $input['hora']
        ]);

        if ($check->fetch()) {
            echo json_encode([
                'ok' => false,
                'message' => 'Ya existe una cita confirmada en ese horario. Elija otro.'
            ]);
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

            // ✔ Verificar que exista la cita
            $existCheck = $db->prepare("SELECT fecha, hora FROM cita WHERE id = :id");
            $existCheck->execute([':id' => (int)$id]);
            $datos = $existCheck->fetch(PDO::FETCH_ASSOC);

            if (!$datos) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Cita no encontrada']);
                exit;
            }

            // ✔ Si va a CONFIRMADA (2), validar que el horario NO esté ocupado
            if ((int)$nuevoEstado === 2) {

                $conflictCheck = $db->prepare("
                    SELECT id FROM cita
                    WHERE fecha = :fecha
                    AND hora = :hora
                    AND id_estado_cita = 3
                    AND id != :id
                    LIMIT 1
                ");
                $conflictCheck->execute([
                    ':fecha' => $datos['fecha'],
                    ':hora'  => $datos['hora'],
                    ':id'    => (int)$id
                ]);

                if ($conflictCheck->fetch()) {
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Ya existe una cita confirmada en ese horario. No se puede confirmar esta.'
                    ]);
                    exit;
                }
                error_log("🟢 cambiarEstado() - ID: $id, nuevoEstado: $nuevoEstado");

            }

            // ✔ Actualizar estado
            $stmt = $db->prepare("UPDATE cita SET id_estado_cita = :estado WHERE id = :id");
            $stmt->execute([':estado' => (int)$nuevoEstado, ':id' => (int)$id]);

            // ✔ Enviar correo al cliente indicando el cambio
            self::enviarAvisoCambioEstado((int)$id);

            echo json_encode(['ok' => true, 'message' => 'Estado de cita actualizado correctamente']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al actualizar estado', 'error' => $e->getMessage()]);
        }
    }

    private static function enviarAvisoCambioEstado(int $idCita): array
    {
        

        try {
            $db = conectarDB();

            $q = $db->prepare("
                SELECT 
                    c.id, c.fecha, c.hora,
                    e.nombre_estado_cita AS estado,
                    v.placa, v.marca, v.modelo, v.carroceria,
                    u.nombre, u.apellido, u.email
                FROM cita c
                JOIN estado_cita e ON e.id = c.id_estado_cita
                JOIN vehiculo v ON v.id = c.id_vehiculo
                JOIN usuario u ON u.id = v.id_usuario
                WHERE c.id = :id
                LIMIT 1
            ");
            $q->execute([':id' => $idCita]);
            $cita = $q->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                error_log("❌ [enviarAvisoCambioEstado] Cita no encontrada ID=$idCita");
                return ['ok' => false];
            }

            // Mensaje según estado
            $estado = strtolower($cita['estado']);
            $mensajeEstado = match ($estado) {
                'confirmada' => 'Tu cita ha sido confirmada ✅',
                'en proceso' => 'Tu revisión está en proceso 🔧',
                'finalizada' => 'Tu revisión ha finalizado ✅',
                'cancelada'  => 'Tu cita fue cancelada ❌',
                'rechazada'  => 'Tu cita fue rechazada ❌',
                default      => "El estado de tu cita cambió a: {$cita['estado']}",
            };

            // Configuración de correo
            cargarEnv(dirname(__DIR__, 2) . '/.env');
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USERNAME');
            $mail->Password = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)(getenv('MAIL_PORT') ?: 2525);

            // Destinatario
            $mail->setFrom('no-reply@tecnocitascda.com', 'TecnoCitasCDA');
            $mail->addAddress($cita['email'], "{$cita['nombre']} {$cita['apellido']}");
            $mail->addCC(getenv('MAIL_TO') ?: 'inbox@mailtrap.io', 'TecnoCitasCDA Notificaciones');

            $mail->isHTML(true);
            $mail->Subject = "Actualización de tu cita: {$cita['estado']}";
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2>Actualización de estado de tu cita</h2>
                    <p>Hola <strong>{$cita['nombre']} {$cita['apellido']}</strong>,</p>
                    <p>{$mensajeEstado}</p>
                    <hr>
                    <p><strong>Detalles de tu cita:</strong></p>
                    <ul>
                        <li><strong>Fecha:</strong> {$cita['fecha']}</li>
                        <li><strong>Hora:</strong> {$cita['hora']}</li>
                        <li><strong>Vehículo:</strong> {$cita['placa']} ({$cita['marca']} {$cita['modelo']})</li>
                        <li><strong>Carrocería:</strong> {$cita['carroceria']}</li>
                    </ul>
                    <p>Gracias por confiar en <strong>TecnoCitasCDA</strong>.</p>
                </div>
            ";

            $mail->AltBody = "Hola {$cita['nombre']}, tu cita ahora está '{$cita['estado']}'.";

            $mail->send();
            error_log("📧 [enviarAvisoCambioEstado] Correo enviado a {$cita['email']} con estado {$cita['estado']}");
            return ['ok' => true];
        } catch (\Exception $e) {
            error_log("❌ Error enviando correo (cambio estado): " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }


    // (Opcional) GET /api/citas/mis-citas  → para rol cliente (listar solo sus vehículos)
    public static function listarCitasPorUsuario()
    {
        verificarRolesPermitidosPorID([2]); // cliente
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

    public static function eliminarCita()
    {
        verificarRolesPermitidosPorID([1, 3]); // admin y técnico

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID no proporcionado']);
            exit;
        }

        try {
            $db = conectarDB();

            // 🔹 Verificar si existe y si está cancelada
            $check = $db->prepare("
                SELECT c.id, e.nombre_estado_cita 
                FROM cita c
                JOIN estado_cita e ON c.id_estado_cita = e.id
                WHERE c.id = :id
            ");
            $check->execute([':id' => (int)$id]);
            $cita = $check->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Cita no encontrada']);
                exit;
            }

            if (strtolower($cita['nombre_estado_cita']) !== 'cancelada') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Solo se pueden eliminar citas canceladas']);
                exit;
            }

            // 🔹 Eliminar cita
            $delete = $db->prepare("DELETE FROM cita WHERE id = :id");
            $delete->execute([':id' => (int)$id]);

            echo json_encode(['ok' => true, 'message' => 'Cita eliminada correctamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al eliminar cita', 'error' => $e->getMessage()]);
        }
    }


}
