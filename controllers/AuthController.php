<?php

namespace Controllers;

use Model\Usuario;

class AuthController
{
    public static function loginApi()
    {
        require_once __DIR__ . '/../includes/cors.php';
        session_start();

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
        session_start();

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
        session_start();

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

}
