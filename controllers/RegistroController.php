<?php
namespace Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

class RegistroController
{
    // POST /api/auth/registro
    public static function registroApi()
    {
        error_log("🟢 Paso 1: Entró a registroApi()");

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        //Manekar CORS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // 🔹 Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            return;
        }

        // 🔹 Leer cuerpo JSON
        $input = json_decode(file_get_contents('php://input'), true);

        $nombre     = s(trim($input['nombre'] ?? ''));
        $apellido   = s(trim($input['apellido'] ?? ''));
        $email      = s(trim($input['email'] ?? ''));
        $telefono   = s(trim($input['telefono'] ?? ''));
        $contrasena = s(trim($input['contrasena'] ?? ''));
        $edad       = intval(trim($input['edad'] ?? 0));
        $documento  = s(trim($input['documento'] ?? ''));



        // 🔹 1. Validación básica
        if (!$nombre || !$apellido || !$email || !$telefono || !$contrasena || !$edad || !$documento) {
            echo json_encode(['ok' => false, 'message' => 'Todos los campos son obligatorios']);
            exit;
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'message' => 'Correo inválido']);
            return;
        }

        if (!preg_match('/^[0-9]{7,15}$/', $telefono)) {
            echo json_encode(['ok' => false, 'message' => 'Teléfono inválido']);
            return;
        }


        $db = conectarDB();
        error_log("🟢 Paso 2: Conexión a la base de datos establecida");


        // 🔹 2. Verificar que no exista ya en la tabla real
        $stmt = $db->prepare("SELECT id FROM usuario WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'El correo ya está registrado']);
            return;
        }

        // 🔹 3. Crear tabla temporal si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS usuario_temporal (
                id SERIAL PRIMARY KEY,
                nombre VARCHAR(100),
                apellido VARCHAR(100),
                email VARCHAR(150) UNIQUE,
                telefono VARCHAR(20),
                edad INT,
                documento VARCHAR(50),
                contraseña VARCHAR(255),
                codigo_verificacion VARCHAR(6)
            )
        ");

        error_log("🟢 Paso 3: Tabla temporal creada o verificada");



        // 🔹 4. Verificar que no esté ya en verificación
        $stmt2 = $db->prepare("SELECT id FROM usuario_temporal WHERE email = :email");
        $stmt2->execute(['email' => $email]);
        if ($stmt2->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'Este correo ya está en verificación']);
            return;
        }

        // 🔹 5. Generar código y guardar temporalmente
        $codigo = rand(100000, 999999);
        $hash   = password_hash($contrasena, PASSWORD_DEFAULT);

        error_log("🟢 Paso 4: Inserción en usuario_temporal completada");

        $ins = $db->prepare("INSERT INTO usuario_temporal 
                (nombre, apellido, email, telefono, edad, documento, contraseña, codigo_verificacion)
                VALUES (:nombre, :apellido, :email, :telefono, :edad, :documento, :contrasena, :codigo)");

            error_log("🟢 Intentando insertar usuario temporal: $email");
            try {
                $ok = $ins->execute([
                    'nombre'     => $nombre,
                    'apellido'   => $apellido,
                    'email'      => $email,
                    'telefono'   => $telefono,
                    'edad'       => $edad,
                    'documento'  => $documento,
                    'contrasena' => $hash,
                    'codigo'     => $codigo
                ]);
                error_log("🟢 Inserción ejecutada correctamente");
            } catch (\PDOException $e) {
                error_log("❌ Error al insertar en usuario_temporal: " . $e->getMessage());
                echo json_encode(['ok' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
                return;
            }




        if (!$ok) {
            echo json_encode(['ok' => false, 'message' => 'Error al guardar usuario temporal']);
            return;
        }

        error_log("🟢 Paso 5: A punto de enviar el correo");

        // 🔹 6. Enviar correo
        error_log("✅ Llegó hasta el punto de enviar el correo a $email");
        $resp = self::enviarCodigo($email, $nombre, $codigo);
        error_log("✅ Terminó self::enviarCodigo()");

        if (!$resp['ok']) {
            echo json_encode([
                'ok' => false,
                'message' => 'Usuario creado pero no se pudo enviar el correo',
                'error' => $resp['error']
            ]);
            return;
        }

        echo json_encode(['ok' => true, 'message' => 'Código enviado. Falta verificar.']);
        exit;
    }

    public static function verificarCodigoApi()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }


        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            return;
        }

        // Leer y validar datos
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $codigoIngresado = trim($input['codigo'] ?? '');

        if (!$email || !$codigoIngresado) {
            echo json_encode(['ok' => false, 'message' => 'Email y código son obligatorios']);
            return;
        }

        $db = conectarDB();

        // Asegurar que la tabla temporal exista
        $db->exec("
            CREATE TABLE IF NOT EXISTS usuario_temporal (
                id SERIAL PRIMARY KEY,
                nombre VARCHAR(100),
                apellido VARCHAR(100),
                email VARCHAR(150) UNIQUE,
                telefono VARCHAR(20),
                edad INT,
                documento VARCHAR(50),
                contraseña VARCHAR(255),
                codigo_verificacion VARCHAR(6)
            )
        ");


        // Buscar usuario en tabla temporal
        $stmt = $db->prepare("SELECT * FROM usuario_temporal WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $usuarioTemp = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Si no existe o ya fue verificado
        if (!$usuarioTemp) {
            echo json_encode(['ok' => false, 'message' => 'Usuario no encontrado o ya verificado']);
            return;
        }

        // Validar el código ingresado
        if ($usuarioTemp['codigo_verificacion'] !== $codigoIngresado) {
            echo json_encode(['ok' => false, 'message' => 'Código incorrecto']);
            return;
        }

        // Insertar en tabla definitiva
        $insert = $db->prepare("
            INSERT INTO usuario (nombre, apellido, email, telefono, edad, documento, contraseña, id_rol_usuario)
            VALUES (:nombre, :apellido, :email, :telefono, :edad, :documento, :contrasena, 2)
        ");

        $ok = $insert->execute([
            'nombre'     => $usuarioTemp['nombre'],
            'apellido'   => $usuarioTemp['apellido'],
            'email'      => $usuarioTemp['email'],
            'telefono'   => $usuarioTemp['telefono'],
            'edad'       => $usuarioTemp['edad'],
            'documento'  => $usuarioTemp['documento'],
            'contrasena' => $usuarioTemp['contraseña']
        ]);

        if (!$ok) {
            echo json_encode(['ok' => false, 'message' => 'Error al registrar el usuario definitivo']);
            return;
        }

        // Eliminar el usuario temporal después de verificarrrrr
        $delete = $db->prepare("DELETE FROM usuario_temporal WHERE email = :email");
        $delete->execute(['email' => $email]);

        echo json_encode(['ok' => true, 'message' => 'Cuenta verificada correctamente']);
    }


    // 🔹 Función privada para enviar correo
    
    private static function enviarCodigo($email, $nombre, $codigo)
    {
        error_log("🟢 Paso 6: Entró a enviarCodigo()");


        $mail = new PHPMailer(true);

        try {
            // Cargar variables de entorno (.env)
            cargarEnv(dirname(__DIR__, 2) . '/.env');

            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USERNAME');
            $mail->Password = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) getenv('MAIL_PORT') ?: 2525;

            // Configurar correo
            $mail->setFrom('no-reply@tecnocitascda.com', 'TecnoCitasCDA');
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = 'Código de verificación';
            $mail->Body = "
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Tu código de verificación es:</p>
                <h2>$codigo</h2>
            ";

            error_log("📬 Intentando enviar correo con PHPMailer a $email");


            $mail->send();
            return ['ok' => true];
        } catch (Exception $e) {
            error_log("❌ Error en PHPMailer: " . $mail->ErrorInfo);

            return ['ok' => false, 'error' => $mail->ErrorInfo];
        }
    } 
}
