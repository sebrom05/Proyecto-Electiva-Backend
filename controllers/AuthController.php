<?php

namespace Controllers;

use Model\Usuario;

class AuthController
{
    // 🔹 LOGIN API
    public static function loginApi()
    {
        require_once __DIR__ . '/../includes/cors.php';
        session_start();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $contraseña = $input['contraseña'] ?? '';

        if (!$email || !$contraseña) {
            echo json_encode(['success' => false, 'message' => 'Correo o contraseña inválidos']);
            return;
        }

        $usuario = Usuario::findByEmail($email);

        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'El usuario no existe']);
            return;
        }

        // Verificar la contraseña
        if (!password_verify($contraseña, $usuario->contraseña)) {
            echo json_encode(['success' => false, 'message' => 'La contraseña es incorrecta']);
            return;
        }

        // Iniciar sesión
        $_SESSION['id'] = $usuario->id;
        $_SESSION['nombre'] = $usuario->nombre;
        $_SESSION['email'] = $usuario->email;
        $_SESSION['rol'] = $usuario->id_rol_usuario;
        $_SESSION['autenticado'] = true;

        unset($usuario->contraseña);

        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'usuario' => $usuario
        ]);
        
    }

    // 🔹 LOGOUT
    public static function logoutApi()
    {
        require_once __DIR__ . '/../includes/cors.php';
        session_start();
        $_SESSION = [];
        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
