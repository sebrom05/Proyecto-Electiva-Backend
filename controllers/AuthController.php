<?php

namespace Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use Model\Usuario;



class AuthController
{
    public static function loginApi()
    {
        require_once __DIR__ . '/../includes/cors.php';

        // Inicializamos arreglo de errores
        $errores = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'errores' => ['Método no permitido']]);
            exit;
        }

        // Leemos los datos JSON del cuerpo
        $input = json_decode(file_get_contents('php://input'), true);
        $email = s(filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL));
        $contrasena = s($input['contrasena'] ?? '');

        // 🔹 Validaciones básicas
        if (!$email) {
            $errores[] = 'El correo electrónico no es válido.';
        }

        if (!$contrasena) {
            $errores[] = 'La contraseña es obligatoria.';
        }

        // Si ya hay errores, devolverlos
        if (!empty($errores)) {
            echo json_encode(['success' => false, 'errores' => $errores]);
            exit;
        }

        // 🔹 Buscar el usuario por correo
        $usuario = Usuario::findByEmail($email);

        if (!$usuario) {
            $errores[] = 'El usuario no existe.';
            echo json_encode(['success' => false, 'errores' => $errores]);
            exit;
        }

        // 🔹 Verificar la contraseña
        if (!password_verify($contrasena, $usuario->contraseña)) {
            $errores[] = 'La contraseña es incorrecta.';
            echo json_encode(['success' => false, 'errores' => $errores]);
            exit;
        }

        // 🔹 Buscar nombre del rol en la tabla rol_usuario
        $db = conectarDB();
        $stmt = $db->prepare("SELECT nombre_rol FROM rol_usuario WHERE id = :id_rol");
        $stmt->execute(['id_rol' => $usuario->id_rol_usuario]);
        $rol = $stmt->fetchColumn() ?: 'Desconocido';

        // 🔹 Si todo está bien: iniciar sesión
        $_SESSION['usuario'] = [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'email' => $usuario->email,
            'rol_id' => $usuario->id_rol_usuario,
            'rol_nombre' => $rol,
            'autenticado' => true
        ];

        $_SESSION['login'] = true;
        $_SESSION['rol'] = strtolower($rol);


        unset($usuario->contraseña);

        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'usuario' => $_SESSION['usuario']
        ]);

        exit;
    }

    public static function verificarSesionApi()
    {
        require_once __DIR__ . '/../includes/cors.php';

        // ✅ Si existe sesión activa, devolvemos los datos del usuario
        if (isset($_SESSION['usuario']) && !empty($_SESSION['usuario']['autenticado'])) {
            echo json_encode([
                'success' => true,
                'usuario' => $_SESSION['usuario']
            ]);
        } else {
            // ❌ No hay sesión activa
            echo json_encode([
                'success' => false,
                'message' => 'No hay sesión activa'
            ]);
        }
        exit;
    }


    public static function logoutApi()
    {
        require_once __DIR__ . '/../includes/cors.php';

        // 🔹 Limpia la sesión
        $_SESSION = [];

        // 🔹 Destruye la sesión activa
        if (session_id()) {
            session_destroy();
        }

        // 🔹 Borra cookie de sesión (muy importante para evitar sesiones "fantasma")
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
        exit;
    }

    public static function recuperarContrasena() {
        require_once __DIR__.'/../includes/cors.php';
        error_log("🟦 Iniciando recuperarContrasena()");

        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        error_log("📩 Email recibido: $email");

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { 
            http_response_code(400); 
            error_log("❌ Email inválido: $email");
            echo json_encode(['ok'=>false,'message'=>'Email inválido']); 
            return; 
        }

        try {
            $db = conectarDB();
            $stmt = $db->prepare("SELECT id,nombre FROM usuario WHERE email=:email");
            $stmt->execute([':email'=>$email]);
            $u = $stmt->fetch(\PDO::FETCH_ASSOC);
            error_log("🔍 Resultado de búsqueda: " . json_encode($u));

            if (!$u) { 
                error_log("⚠️ Usuario no encontrado, no se revela existencia.");
                echo json_encode(['ok'=>true]); 
                return; 
            }

            $codigo = rand(100000, 999999);
            error_log("🔢 Código generado: $codigo");

            // Verificamos si la tabla existe o no
            $db->exec("CREATE TABLE IF NOT EXISTS recuperacion (
                email varchar(150) primary key, 
                codigo varchar(6), 
                creado timestamp default now()
            )");

            $db->prepare("DELETE FROM recuperacion WHERE email=:e")->execute([':e'=>$email]);
            $db->prepare("INSERT INTO recuperacion(email,codigo) VALUES(:e,:c)")
            ->execute([':e'=>$email, ':c'=>$codigo]);
            error_log("💾 Código guardado en tabla recuperacion.");

            self::enviarCorreoGenerico(
                $email,
                $u['nombre'],
                "Recuperación de contraseña - TecnoCitasCDA",
                "
                <div style='font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px; border-radius: 8px;'>
                    <h2 style='color:#0d6efd; text-align:center;'>🔐 Recuperación de contraseña</h2>
                    <p>Hola <strong>{$u['nombre']}</strong>,</p>
                    <p>Recibimos una solicitud para restablecer tu contraseña en <strong>TecnoCitasCDA</strong>.</p>
                    <p>Tu código de verificación es:</p>
                    <div style='text-align:center; margin:20px 0;'>
                        <h1 style='color:#198754; font-size:36px; letter-spacing:4px;'>$codigo</h1>
                    </div>
                    <p>Este código es válido por <strong>15 minutos</strong>. Si no solicitaste el cambio, puedes ignorar este mensaje.</p>
                    <br>
                    <p style='text-align:center; color:#6c757d;'>© ".date('Y')." TecnoCitasCDA. Todos los derechos reservados.</p>
                </div>
                "
            );

            error_log("📤 Correo de recuperación enviado a: $email");

            echo json_encode(['ok'=>true,'message'=>'Código enviado']);
        } catch (\Throwable $e) {
            error_log("❗ Error en recuperarContrasena(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok'=>false,'message'=>'Error interno']);
        }
    }

    public static function verificarRecuperacion() {
        require_once __DIR__.'/../includes/cors.php';
        error_log("🟦 Iniciando verificarRecuperacion()");

        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $codigo = trim($input['codigo'] ?? '');
        error_log("📩 Datos recibidos - Email: $email | Código: $codigo");

        try {
            $db = conectarDB();
            $stmt = $db->prepare("SELECT 1 FROM recuperacion WHERE email=:e AND codigo=:c AND creado>now()-interval '15 minutes'");
            $stmt->execute([':e'=>$email, ':c'=>$codigo]);
            $ok = (bool)$stmt->fetchColumn();

            error_log("🔍 Verificación del código: " . ($ok ? "✅ Válido" : "❌ Inválido o expirado"));
            echo json_encode(['ok'=>$ok]);
        } catch (\Throwable $e) {
            error_log("❗ Error en verificarRecuperacion(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok'=>false,'message'=>'Error interno']);
        }
    }

    public static function cambiarContrasena() {
        require_once __DIR__.'/../includes/cors.php';
        error_log("🟦 Iniciando cambiarContrasena()");

        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $codigo = trim($input['codigo'] ?? '');
        $pass = $input['nueva'] ?? '';

        error_log("📩 Datos recibidos - Email: $email | Código: $codigo | Nueva longitud: " . strlen($pass));

        if (strlen($pass) < 6) {
            http_response_code(400);
            error_log("❌ Contraseña demasiado corta.");
            echo json_encode(['ok'=>false,'message'=>'Contraseña muy corta']); 
            return;
        }

        try {
            $db = conectarDB();
            $v = $db->prepare("SELECT 1 FROM recuperacion WHERE email=:e AND codigo=:c AND creado>now()-interval '15 minutes'");
            $v->execute([':e'=>$email, ':c'=>$codigo]);

            if (!$v->fetchColumn()) {
                http_response_code(400);
                error_log("❌ Código inválido o expirado para $email");
                echo json_encode(['ok'=>false,'message'=>'Código inválido/expirado']); 
                return;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE usuario SET contraseña=:p WHERE email=:e")->execute([':p'=>$hash, ':e'=>$email]);
            $db->prepare("DELETE FROM recuperacion WHERE email=:e")->execute([':e'=>$email]);
            error_log("🔄 Contraseña actualizada correctamente para $email");

            echo json_encode(['ok'=>true,'message'=>'Contraseña actualizada']);
        } catch (\Throwable $e) {
            error_log("❗ Error en cambiarContrasena(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok'=>false,'message'=>'Error interno']);
        }
    }

    // Reutiliza tu PHPMailer ya montado
    private static function enviarCorreoGenerico($email, $nombre, $asunto, $html) {
        error_log("📧 Iniciando enviarCorreoGenerico() ");

        // Cargamos PHPMailer
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../includes/config/database.php'; // por si necesitas conectar

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // 📂 Cargar variables de entorno
            cargarEnv(dirname(__DIR__, 2) . '/.env');
            error_log("🟢 Variables .env cargadas correctamente");

            // ⚙️ Configuración SMTP (idéntica a RegistroController)
            $mail->isSMTP();
            $mail->Host       = getenv('MAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('MAIL_USERNAME');
            $mail->Password   = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) getenv('MAIL_PORT') ?: 587;

            error_log("📡 Configuración SMTP: Host={$mail->Host}, Usuario={$mail->Username}, Puerto={$mail->Port}");

            // 🧾 Remitente y destinatario
            $mail->setFrom('no-reply@tecnocitascda.com', 'TecnoCitasCDA');
            $mail->addAddress($email, $nombre);

            // ✉️ Contenido
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags($html);

            error_log("📬 Intentando enviar correo a $email...");
            $mail->send();

            error_log("✅ Correo enviado correctamente a $email con asunto '$asunto'");
            return ['ok' => true];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("❌ Error PHPMailer Exception: " . $e->getMessage());
            error_log("❌ Detalle interno: " . $mail->ErrorInfo);
            return ['ok' => false, 'error' => $mail->ErrorInfo];
        } catch (\Throwable $e) {
            error_log("❌ Error general al enviar correo: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }




}
