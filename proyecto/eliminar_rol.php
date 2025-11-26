<?php
session_start();
require_once "conexion.php";

// (Incluir aquí la función registrarAuditoria si aplica)

$conexion = conectar();

// Verificar si el usuario es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

// Verificar si se recibió el ID
if (!isset($_GET['id'])) {
    header("Location: roles.php"); // O la página que corresponda
    exit();
}

$id_rol = intval($_GET['id']);

// --- VERIFICACIÓN CLAVE ---
// Verificar si hay usuarios en cualquier tabla que usen este rol_id
// Suponiendo que las tablas relevantes son: estudiante, profesores, secretarios, directivos, super_usuario
// Y que todas tienen una columna 'rol_id' que referencia al rol.

$tablas_con_rol = [
    'estudiante',
    'profesores',
    'secretarios',
    'directivos',
    'super_usuario' // Aunque posiblemente solo haya uno, es bueno verificar
];

$usuarios_relacionados = false;
foreach ($tablas_con_rol as $tabla) {
    $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM `$tabla` WHERE rol_id = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $id_rol);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $usuarios_relacionados = true;
            // Opcional: Romper el loop en cuanto se encuentre una coincidencia
            break;
        }
    } else {
        // Manejar error de preparación de sentencia si es necesario
        // die("Error en la consulta de verificación: " . $conexion->error); // No ideal para producción
        // Podrías querer registrar este error y redirigir o mostrar un mensaje genérico.
        // Por simplicidad aquí, asumiremos que las tablas existen y la consulta funciona.
    }
}

if ($usuarios_relacionados) {
    // Si hay usuarios, no se puede eliminar el rol
    // Registrar intento fallido en auditoría (si aplica)
    // registrarAuditoria(..., 'ELIMINAR_ROL', 'FALLIDO', 'Rol tiene usuarios asociados', ...);

    // Mostrar mensaje de error al usuario (puedes usar sesiones para mensajes flash)
    $_SESSION['mensaje_error'] = "No se puede eliminar el rol porque hay usuarios asociados a él.";
    header("Location: roles.php"); // Redirigir de vuelta a la lista de roles
    exit();
}
// --- FIN VERIFICACIÓN ---

// Si llega aquí, es porque NO hay usuarios con ese rol_id
// Obtener nombre del rol antes de eliminarlo para auditoría (opcional)
$stmt_select_nombre = $conexion->prepare("SELECT nombre FROM roles WHERE id = ?");
$stmt_select_nombre->bind_param("i", $id_rol);
$stmt_select_nombre->execute();
$result_nombre = $stmt_select_nombre->get_result();
$nombre_rol_eliminado = null;
if ($row_nombre = $result_nombre->fetch_assoc()) {
    $nombre_rol_eliminado = $row_nombre['nombre'];
}
$stmt_select_nombre->close();

// Proceder con la eliminación
$stmt_delete = $conexion->prepare("DELETE FROM roles WHERE id = ?");
$stmt_delete->bind_param("i", $id_rol);

if ($stmt_delete->execute()) {
    if ($stmt_delete->affected_rows > 0) {
        // Registrar éxito en auditoría (si aplica)
        // registrarAuditoria(..., 'ELIMINAR_ROL', 'EXITO', null, 'roles', $id_rol, 'nombre', $nombre_rol_eliminado, null);

        $mensaje_exito = "Rol eliminado correctamente.";
        // Opcional: Pasar mensaje de éxito a través de sesión
        // $_SESSION['mensaje_exito'] = $mensaje_exito;
    } else {
        // Esto sería raro, el rol no existía aunque pasó la verificación anterior
        // registrarAuditoria(..., 'ELIMINAR_ROL', 'FALLIDO', 'Rol no encontrado tras verificación', ...);
        $mensaje_error = "El rol no existía.";
        // $_SESSION['mensaje_error'] = $mensaje_error;
    }
} else {
    // Error en la consulta DELETE
    // registrarAuditoria(..., 'ELIMINAR_ROL', 'FALLIDO', $conexion->error, ...);
    $mensaje_error = "Error al eliminar el rol: " . $conexion->error;
    // $_SESSION['mensaje_error'] = $mensaje_error;
}

$stmt_delete->close();
$conexion->close();

// Redirigir
header("Location: roles.php");
exit();

?>