<?php
session_start();
require_once "conexion.php";

// Función para registrar en la auditoría (añadida aquí)
function registrarAuditoria($conn, $id_usuario, $tipo_usuario, $usuario_nombre, $accion, $resultado, $motivo = null, $objeto_afectado = null, $id_objeto = null, $campo_modificado = null, $valor_anterior = null, $valor_nuevo = null) {
    $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id();

    $sql_auditoria = "INSERT INTO auditoria (
        id_usuario, tipo_usuario, usuario_nombre, accion, resultado, motivo_fallo,
        objeto_afectado, id_objeto, campo_modificado, valor_anterior, valor_nuevo,
        ip_origen, user_agent, session_id, fecha_hora
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

    $stmt_auditoria = $conn->prepare($sql_auditoria);
    if ($stmt_auditoria) {
        $stmt_auditoria->bind_param(
            "isssssisssssss", // 14 tipos: i s s s s s s i s s s s s s
            $id_usuario,
            $tipo_usuario,
            $usuario_nombre,
            $accion,
            $resultado,
            $motivo,
            $objeto_afectado,
            $id_objeto, // Este es un entero, por lo tanto, tipo 'i'
            $campo_modificado,
            $valor_anterior,
            $valor_nuevo,
            $ip_origen,
            $user_agent,
            $session_id
        );
        $stmt_auditoria->execute();
        $stmt_auditoria->close();
    }
}

// Verificar que es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

// Validar que venga el ID por GET
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    echo "<script>alert('ID de usuario no válido.'); window.location='usuarios.php';</script>";
    exit();
}

$id = intval($_GET["id"]);
$conn = conectar();

// Obtener datos del usuario a eliminar (incluyendo rol para lógica de restricción)
$sql_user_data = "
    SELECT u.id, u.rol_id, r.nombre AS rol_nombre
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    WHERE u.id = ?";
$stmt_user_data = $conn->prepare($sql_user_data);
$stmt_user_data->bind_param("i", $id);
$stmt_user_data->execute();
$res_user_data = $stmt_user_data->get_result();

if ($res_user_data->num_rows === 0) {
    // Registrar intento fallido
    $usuario_nombre_sesion = $_SESSION["usuario"];
    $id_usuario_sesion = $_SESSION["id_usuario"];
    $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'ELIMINAR_USUARIO', 'FALLIDO', 'Usuario no encontrado', 'usuarios', $id);
    echo "<script>alert('El usuario no existe.'); window.location='usuarios.php';</script>";
    exit();
}

$usuario_datos = $res_user_data->fetch_assoc();
$rol_nombre_usuario = $usuario_datos['rol_nombre']; // Por ejemplo, 'estudiante', 'profesor', etc.

// Evitar que un super_usuario se elimine a sí mismo (comparando ID de sesión con ID del usuario a eliminar)
if ($_SESSION["id_usuario"] == $id) { // Cambiado de $_SESSION["usuario"] (nombre) a $_SESSION["id_usuario"] (ID)
    // Registrar intento fallido
    $usuario_nombre_sesion = $_SESSION["usuario"];
    $id_usuario_sesion = $_SESSION["id_usuario"];
    $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'ELIMINAR_USUARIO', 'FALLIDO', 'Autoeliminación prohibida', 'usuarios', $id);
    echo "<script>alert('No puedes eliminar tu propia cuenta.'); window.location='usuarios.php';</script>";
    exit();
}

// --- VERIFICACIÓN DE RESTRICCIONES ---
// Solo aplicar restricciones si el rol del usuario a eliminar es 'estudiante'
if ($rol_nombre_usuario === 'estudiante') {
    // Definir tablas relacionadas con el estudiante que impiden la eliminación
    $tablas_restriccion = [
        'analitico' => 'id_estudiante', // Tabla analítico, campo id_estudiante
        'asistencias' => 'id_usuario', // Asumiendo que asistencias también usa id_usuario para referenciar al estudiante
        // Añadir aquí otras tablas si existen y deben restringir la eliminación
        // 'inscripciones_finales' => 'id_estudiante',
        // 'otra_tabla' => 'id_usuario',
    ];

    $tiene_registros = false;
    $tabla_conflicto = '';

    foreach ($tablas_restriccion as $tabla => $campo_fk) {
        $sql_check = "SELECT COUNT(*) as total FROM `$tabla` WHERE `$campo_fk` = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $row_check = $result_check->fetch_assoc();
            if ($row_check['total'] > 0) {
                $tiene_registros = true;
                $tabla_conflicto = $tabla;
                $stmt_check->close();
                break; // Salir del loop al encontrar la primera coincidencia
            }
            $stmt_check->close();
        } else {
            // Si hay un error al preparar la consulta, también es un problema
            // Podrías elegir manejarlo de otra manera, pero por simplicidad aquí se detiene
            error_log("Error en la preparación de la consulta de verificación en eliminar_usuario.php para la tabla $tabla: " . $conn->error);
            echo "<script>alert('Error interno al verificar restricciones.'); window.location='usuarios.php';</script>";
            exit();
        }
    }

    if ($tiene_registros) {
        // Registrar intento fallido por restricción
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        $motivo = "Usuario tiene registros en la tabla '$tabla_conflicto'";
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'ELIMINAR_USUARIO', 'FALLIDO', $motivo, 'usuarios', $id);
        echo "<script>alert('No se puede eliminar el usuario porque tiene registros en la tabla \"$tabla_conflicto\" (por ejemplo, analítico, asistencias).'); window.location='usuarios.php';</script>";
        exit();
    }
}
// --- FIN VERIFICACIÓN ---

// Si llega aquí, es porque:
// 1. El usuario existe.
// 2. No es el propio super_usuario quien se intenta eliminar.
// 3. (Si es estudiante) No tiene registros que impidan la eliminación según nuestras reglas.

// Opcional: Obtener nombre del usuario para auditoría antes de eliminarlo
$nombre_usuario_eliminar = "Usuario_$id"; // Valor por defecto
$sql_nombre = "SELECT nombre, apellido FROM usuarios WHERE id = ?";
$stmt_nombre = $conn->prepare($sql_nombre);
if ($stmt_nombre) {
    $stmt_nombre->bind_param("i", $id);
    $stmt_nombre->execute();
    $result_nombre = $stmt_nombre->get_result();
    if ($row_nombre = $result_nombre->fetch_assoc()) {
        $nombre_usuario_eliminar = $row_nombre['nombre'] . ' ' . $row_nombre['apellido'];
    }
    $stmt_nombre->close();
}


// Eliminar usuario
$sql_delete = "DELETE FROM usuarios WHERE id = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    if ($stmt_delete->affected_rows > 0) {
        // Registrar éxito
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'ELIMINAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'nombre_apellido', $nombre_usuario_eliminar, null);
        echo "<script>alert('Usuario eliminado correctamente.'); window.location='usuarios.php';</script>";
    } else {
        // No se eliminó ningún registro, posiblemente porque el ID ya no existía en este punto
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'ELIMINAR_USUARIO', 'FALLIDO', 'Usuario ya no existe al intentar eliminarlo', 'usuarios', $id);
        echo "<script>alert('El usuario ya no existía o ya había sido eliminado.'); window.location='usuarios.php';</script>";
    }
} else {
    // Error en la consulta DELETE
    // Registrar intento fallido
    $usuario_nombre_sesion = $_SESSION["usuario"];
    $id_usuario_sesion = $_SESSION["id_usuario"];
    $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'ELIMINAR_USUARIO', 'FALLIDO', $stmt_delete->error, 'usuarios', $id);
    echo "<script>alert('Error al eliminar usuario: " . $stmt_delete->error . "'); window.location='usuarios.php';</script>";
}

$stmt_delete->close();
$conn->close();
?>