<?php
namespace Controllers;

require_once __DIR__ . '/../includes/cors.php';

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
                    v.id_usuario,
                    v.id_tipo_vehiculo,
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
        error_log("🧠 Iniciando método editarVehiculo()");
        verificarRolesPermitidosPorID([1, 3]); // Admin y Técnico

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        try {
            $db = conectarDB();

            // 🔹 Imagen (opcional)
            $id = $_POST['id'] ?? null;
            $nombreImagen = null;

            if (!empty($_FILES['imagen']['name'])) {
                $nombreImagen = self::subirImagenVehiculo($_FILES['imagen'], $id);
            }

            if (!$id) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'ID de vehículo requerido']);
                exit;
            }

            $campos = [
                'id_usuario' => $_POST['id_usuario'] ?? null,
                'id_tipo_vehiculo' => $_POST['id_tipo_vehiculo'] ?? null,
                'placa' => $_POST['placa'] ?? null,
                'marca' => $_POST['marca'] ?? null,
                'modelo' => $_POST['modelo'] ?? null,
                'carroceria' => $_POST['carroceria'] ?? null,
                'fecha_tecnomecanica' => (!empty($_POST['fecha_tecnomecanica']) && $_POST['fecha_tecnomecanica'] !== 'null')
                ? $_POST['fecha_tecnomecanica']
                : null

            ];

            if ($nombreImagen) {
                $campos['imagen'] = $nombreImagen;
            }

            $set = [];
            $params = [];
            foreach ($campos as $key => $value) {
                if (!is_null($value)) {
                    $set[] = "$key = :$key";
                    $params[$key] = $value;
                }
            }

            if (empty($set)) {
                echo json_encode(['ok' => false, 'message' => 'No hay campos para actualizar']);
                exit;
            }

            $query = "UPDATE vehiculo SET " . implode(', ', $set) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            $params['id'] = $id;
            $stmt->execute($params);

            error_log("✅ Vehículo actualizado correctamente (ID: $id)");
            echo json_encode(['ok' => true, 'message' => 'Vehículo actualizado correctamente']);
        } catch (Exception $e) {
            error_log("❌ Error en editarVehiculo: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al editar vehículo',
                'error' => $e->getMessage()
            ]);
        }
    }


   private static function subirImagenVehiculo($imagenArchivo, $idVehiculo = null)
    {
        try {
            if (empty($imagenArchivo['name'])) {
                error_log("📷 [subirImagenVehiculo] No se recibió imagen.");
                return null;
            }

            // Si hay ID, eliminar imagen anterior
            if ($idVehiculo) {
                $db = conectarDB();
                $stmt = $db->prepare("SELECT imagen FROM vehiculo WHERE id = :id");
                $stmt->execute(['id' => $idVehiculo]);
                $actual = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($actual && !empty($actual['imagen'])) {
                    $rutaAntigua = CARPETA_IMAGENES . $actual['imagen'];
                    if (file_exists($rutaAntigua)) {
                        unlink($rutaAntigua);
                        error_log("🗑️ Imagen anterior eliminada: " . $rutaAntigua);
                    }
                }
            }

            // Subir nueva imagen
            $nombreOriginal = basename($imagenArchivo['name']);
            $nombreLimpio = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $nombreOriginal); // reemplaza espacios y caracteres raros
            $nombreImagen = uniqid('vehiculo_') . '_' . $nombreLimpio;

            $rutaDestino = CARPETA_IMAGENES . $nombreImagen;

            if (!move_uploaded_file($imagenArchivo['tmp_name'], $rutaDestino)) {
                throw new Exception("Error al mover el archivo a destino.");
            }

            error_log("✅ Imagen subida correctamente: " . $rutaDestino);
            return $nombreImagen;

        } catch (Exception $e) {
            error_log("❌ [subirImagenVehiculo] " . $e->getMessage());
            return null;
        }
    }



    public static function crearVehiculo()
    {
        error_log("🚗 [crearVehiculo] Inicio");
        verificarRolesPermitidosPorID([1, 3]); // Admin o técnico
        error_log("✅ [crearVehiculo] Rol OK. Método: " . $_SERVER['REQUEST_METHOD']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("⛔ [crearVehiculo] Método no permitido: " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            
            exit;
        }

        try {
            // 🧩 Soporte para formulario con imagen
            $nombreImagen = self::subirImagenVehiculo($_FILES['imagen']);


            // Campos normales (enviados por JSON o formulario)
            $input = $_POST ?: json_decode(file_get_contents('php://input'), true);
            error_log("🧾 [crearVehiculo] Datos recibidos: " . json_encode($input));
            if (empty($input['placa']) || empty($input['marca']) || empty($input['modelo']) || empty($input['id_usuario'])) {
                error_log("⚠️ [crearVehiculo] Datos incompletos detectados");
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
                exit;
            }

            $db = conectarDB();
            error_log("✅ [crearVehiculo] Conexión a BD establecida correctamente");
            $stmt = $db->prepare("
                INSERT INTO vehiculo (id_usuario, id_tipo_vehiculo, placa, marca, modelo, carroceria, imagen, activo, fecha_registro)
                VALUES (:id_usuario, :id_tipo_vehiculo, :placa, :marca, :modelo, :carroceria, :imagen, true, NOW())
            ");

            error_log("📦 [crearVehiculo] Parámetros antes de ejecutar: " . json_encode([
                'id_usuario' => $input['id_usuario'],
                'placa' => $input['placa'],
                'marca' => $input['marca'],
                'modelo' => $input['modelo'],
                'carroceria' => $input['carroceria'] ?? null,
                'imagen' => $nombreImagen
            ]));

            
            $stmt->execute([
                ':id_usuario'  => $input['id_usuario'],
                ':id_tipo_vehiculo' => $input['id_tipo_vehiculo'],  // 🔥 Nuevo campo obligatorio
                ':placa'       => strtoupper(trim($input['placa'])),
                ':marca'       => $input['marca'],
                ':modelo'      => $input['modelo'],
                ':carroceria'  => $input['carroceria'] ?? null,
                ':imagen'      => $nombreImagen
            ]);
            error_log("✅ [crearVehiculo] Vehículo insertado correctamente en la BD");

            echo json_encode(['ok' => true, 'message' => 'Vehículo registrado correctamente']);
        } catch (Exception $e) {
            error_log("❌ [crearVehiculo] Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al registrar vehículo',
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function listarTiposVehiculo()
    {
        verificarRolesPermitidosPorID([1,2, 3]);
        try {
            $db = conectarDB();
            $stmt = $db->query("SELECT id, nombre_tipo_vehiculo FROM tipo_vehiculo ORDER BY id ASC");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'tipos' => $tipos]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al listar tipos de vehículo',
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function estadisticasVehiculos()
    {
        verificarRolesPermitidosPorID([1]); // solo admin

        try {
            $db = conectarDB();

            // 🔹 Vehículos por tipo
            $stmt = $db->query("
                SELECT tv.nombre_tipo_vehiculo AS tipo, COUNT(*) AS total
                FROM vehiculo v
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                GROUP BY tv.nombre_tipo_vehiculo
            ");
            $porTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 🔹 Vehículos por marca
            $stmt = $db->query("
                SELECT marca, COUNT(*) AS total
                FROM vehiculo
                GROUP BY marca
                ORDER BY total DESC
                LIMIT 5
            ");
            $porMarca = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 🔹 Vehículos por estado (activo/inactivo)
            $stmt = $db->query("
                SELECT 
                    CASE WHEN activo = TRUE THEN 'Activo' ELSE 'Inactivo' END AS estado,
                    COUNT(*) AS total
                FROM vehiculo
                GROUP BY activo
            ");
            $porEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'porTipo' => $porTipo,
                'porMarca' => $porMarca,
                'porEstado' => $porEstado
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al obtener estadísticas de vehículos',
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function estadisticasPorModeloYMes()
    {
        verificarRolesPermitidosPorID([1]); // solo admin

        try {
            $db = conectarDB();

            // 🔹 Vehículos por año/modelo
            $stmt = $db->query("
                SELECT modelo, COUNT(*) AS total
                FROM vehiculo
                GROUP BY modelo
                ORDER BY modelo ASC
            ");
            $porModelo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 🔹 Vehículos registrados por mes
            $stmt = $db->query("
                SELECT TO_CHAR(fecha_registro, 'YYYY-MM') AS mes, COUNT(*) AS total
                FROM vehiculo
                GROUP BY mes
                ORDER BY mes ASC
            ");
            $porMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'porModelo' => $porModelo,
                'porMes' => $porMes
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al obtener estadísticas por modelo y mes',
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function listarVehiculosPorUsuario()
    {


        if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']['id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'No autenticado']);
            exit;
        }

        $idUsuario = $_SESSION['usuario']['id'];

        try {
            $db = conectarDB();
            $stmt = $db->prepare("
                SELECT 
                    v.id,
                    v.placa,
                    v.marca,
                    v.modelo,
                    v.carroceria,
                    v.fecha_tecnomecanica,
                    v.imagen,
                    v.activo
                FROM vehiculo v
                WHERE v.id_usuario = :idUsuario
                ORDER BY v.id DESC
            ");
            $stmt->bindValue(':idUsuario', $idUsuario, PDO::PARAM_INT);
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
                'message' => 'Error al listar los vehículos del usuario',
                'error' => $e->getMessage()
            ]);
        }
    }

    // ==========================================
    // 🔹 4. Cliente: registrar su propio vehículo (debug)
    // ==========================================
    public static function crearVehiculoCliente()
    {

        error_log("🚗 [crearVehiculoCliente] --- INICIO ---");

        // 🔸 Validar sesión
        if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']['id'])) {
            error_log("❌ [crearVehiculoCliente] No hay sesión activa");
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Debe iniciar sesión.']);
            exit;
        }

        $idUsuario = $_SESSION['usuario']['id'];
        error_log("🧑‍💻 [crearVehiculoCliente] Usuario autenticado con ID: $idUsuario");

        // 🔸 Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("⛔ [crearVehiculoCliente] Método incorrecto: " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        try {
            // 🔸 Imagen (opcional)
            $nombreImagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                error_log("📸 [crearVehiculoCliente] Imagen recibida: " . $_FILES['imagen']['name']);
                $nombreImagen = self::subirImagenVehiculo($_FILES['imagen']);
                error_log("✅ [crearVehiculoCliente] Imagen guardada como: " . $nombreImagen);
            } else {
                error_log("⚠️ [crearVehiculoCliente] No se envió imagen");
            }

            // 🔸 Datos recibidos
            $input = $_POST ?: json_decode(file_get_contents('php://input'), true);
            error_log("📦 [crearVehiculoCliente] Datos recibidos: " . json_encode($input));

            if (empty($input['placa']) || empty($input['marca']) || empty($input['modelo'])) {
                error_log("⚠️ [crearVehiculoCliente] Datos obligatorios faltantes");
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Faltan datos obligatorios']);
                exit;
            }

            // 🔹 Conexión a BD
            $db = conectarDB();
            error_log("✅ [crearVehiculoCliente] Conectado a la base de datos correctamente");

            // 🔹 Insertar registro
            $stmt = $db->prepare("
                INSERT INTO vehiculo 
                (id_usuario, id_tipo_vehiculo, placa, marca, modelo, carroceria, imagen, activo, fecha_registro)
                VALUES (:id_usuario, :id_tipo_vehiculo, :placa, :marca, :modelo, :carroceria, :imagen, true, NOW())
            ");

            $params = [
                ':id_usuario'       => $idUsuario,
                ':id_tipo_vehiculo' => $input['id_tipo_vehiculo'] ?? 1,
                ':placa'            => strtoupper(trim($input['placa'])),
                ':marca'            => trim($input['marca']),
                ':modelo'           => $input['modelo'],
                ':carroceria'       => $input['carroceria'] ?? null,
                ':imagen'           => $nombreImagen
            ];

            error_log("🧾 [crearVehiculoCliente] Parámetros: " . json_encode($params));
            $stmt->execute($params);

            error_log("✅ [crearVehiculoCliente] Vehículo insertado correctamente en la base de datos");

            echo json_encode(['ok' => true, 'message' => 'Vehículo registrado correctamente']);
        } catch (Exception $e) {
            error_log("❌ [crearVehiculoCliente] Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Error al registrar vehículo del cliente',
                'error' => $e->getMessage()
            ]);
        }
    }






}