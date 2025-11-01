<?php
namespace Controllers;

use Model\Usuario; 

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
}
