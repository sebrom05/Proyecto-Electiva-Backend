<?php
namespace Controllers;

use Model\Usuario; 

use PDO;
use Exception;

class UsuarioController {

    public function listar() {
        try {
            $usuarios = Usuario::all();

            echo json_encode([
                "ok" => true,
                "data" => $usuarios
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                "ok" => false,
                "message" => "Error al obtener los usuarios",
                "error" => $e->getMessage()
            ]);
        }
    }

    public static function crearUsuario()
    {
        error_log("🧠 Entrando a crearUsuario()");

        verificarRolesPermitidosPorID([1]); // Solo admin

        error_log("✅ Rol verificado correctamente");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("🚫 Método incorrecto: " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        error_log("📥 Datos recibidos RAW: " . json_encode($input));

        if (empty($input['nombre']) || empty($input['apellido']) || empty($input['email']) || empty($input['id_rol_usuario'])) {
            error_log("⚠️ Faltan datos obligatorios");
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        try {
            $db = conectarDB();
            error_log("✅ Conexión a BD establecida correctamente");
            $stmt = $db->prepare("
                INSERT INTO usuario (id_rol_usuario, nombre, apellido, email, contraseña, edad, documento, telefono)
                VALUES (:id_rol_usuario, :nombre, :apellido, :email, :contrasena, :edad, :documento, :telefono)
            ");
            $stmt->execute([
                ':id_rol_usuario' => $input['id_rol_usuario'],
                ':nombre' => $input['nombre'],
                ':apellido' => $input['apellido'],
                ':email' => $input['email'],
                ':contrasena' => password_hash($input['contrasena'] ?? '123456', PASSWORD_DEFAULT),
                ':edad' => $input['edad'] ?? null,
                ':documento' => $input['documento'] ?? null,
                ':telefono' => $input['telefono'] ?? null
            ]);

            error_log("✅ Usuario creado correctamente en la BD");

            echo json_encode(['ok' => true, 'message' => 'Usuario creado correctamente']);
        } catch (Exception $e) {
            error_log("❌ Error en crearUsuario: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Error al crear usuario', 'error' => $e->getMessage()]);
        }
    }

}
