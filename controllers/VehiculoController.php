<?php
namespace Controllers;

use PDO;
use Exception;

class VehiculoController
{
    public static function listarVehiculos()
    {
        verificarRolesPermitidosPorID([1, 3]); // ✅ Solo admin (1) y técnico (3)

        try {
            $db = conectarDB();

            $stmt = $db->prepare("
                SELECT 
                    v.id,
                    v.placa,
                    v.marca,
                    v.modelo,
                    v.carroceria,
                    v.imagen,
                    v.activo,
                    v.fecha_registro,
                    v.fecha_tecnomecanica,
                    CASE 
                        WHEN v.activo = TRUE THEN 'Activo'
                        ELSE 'Inactivo'
                    END AS estado,
                    CONCAT(u.nombre, ' ', u.apellido) AS propietario
                FROM vehiculo v
                LEFT JOIN usuario u ON v.id_usuario = u.id
                ORDER BY v.id DESC
            ");
            $stmt->execute();

            $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'vehiculos' => $vehiculos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al listar vehículos.',
                'error' => $e->getMessage()
            ]);
        }
    }

    // ==========================================
    // 🔹 2. Cambiar estado (activar / desactivar)
    // ==========================================
    public static function cambiarEstadoVehiculo()
    {
        verificarRolesPermitidosPorID([1, 3]); // Admin y Técnico

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        error_log("📦 Datos recibidos en cambiarEstadoVehiculo: " . json_encode($input));
        error_log("📦 Tipo de id: " . gettype($input['id']));
        $id = $input['id'] ?? null;

        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID de vehículo no válido']);
            exit;
        }

        try {
            $db = conectarDB();
            error_log("🔍 Consultando estado del vehículo con ID = $id");

            // 🔹 Obtener estado actual correctamente
            $stmt = $db->prepare("SELECT activo FROM vehiculo WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Vehículo no encontrado']);
                exit;
            }

            $actual = $row['activo'];
            error_log("🔍 Valor devuelto por SELECT activo: " . var_export($actual, true));


             // 🔹 Normalizamos para convertir 't'/'f' en boolean real
            $estadoActual = ($actual === true || $actual === 't' || $actual === '1');

            // 🔹 Alternar el estado
            $nuevo = $estadoActual ? false : true;

            // 🔹 Actualizar en la base de datos
            // 🔹 Actualizar el estado
            $update = $db->prepare("UPDATE vehiculo SET activo = :nuevo WHERE id = :id");
            $update->bindValue(':nuevo', $nuevo ? 't' : 'f', PDO::PARAM_STR);
            $update->bindValue(':id', $id, PDO::PARAM_INT);
            $update->execute();


            error_log("✅ Vehículo actualizado correctamente. ID=$id, Nuevo estado=$nuevo");

            echo json_encode([
                'ok' => true,
                'message' => 'Estado actualizado correctamente',
                'nuevo_estado' => $nuevo ? 'Activo' : 'Inactivo'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al cambiar estado del vehículo',
                'error' => $e->getMessage()
            ]);
        }
    }


    // ==========================================
    // 🔹 3. Editar datos del vehículo
    // ==========================================
    public static function editarVehiculo()
    {
        verificarRolesPermitidosPorID([1, 3]); // Admin y Técnico

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID de vehículo requerido']);
            exit;
        }

        try {
            $db = conectarDB();

            $campos = [
                'placa' => $input['placa'] ?? null,
                'marca' => $input['marca'] ?? null,
                'modelo' => $input['modelo'] ?? null,
                'carroceria' => $input['carroceria'] ?? null,
                'fecha_tecnomecanica' => $input['fecha_tecnomecanica'] ?? null
            ];

            $set = [];
            foreach ($campos as $key => $value) {
                if (!is_null($value)) $set[] = "$key = :$key";
            }

            if (empty($set)) {
                echo json_encode(['ok' => false, 'message' => 'No hay campos para actualizar']);
                exit;
            }

            $query = "UPDATE vehiculo SET " . implode(', ', $set) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            $campos['id'] = $id;
            $stmt->execute($campos);

            echo json_encode(['ok' => true, 'message' => 'Vehículo actualizado correctamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al editar vehículo',
                'error' => $e->getMessage()
            ]);
        }
    }
}