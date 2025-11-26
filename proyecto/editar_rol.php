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

$conexion = conectar(); // Inicializamos la conexión

// Verificar si el usuario es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

$usuario = $_SESSION["usuario"];
$mensaje = "";

// Verificar si se recibió el ID
if (!isset($_GET['id'])) {
    header("Location: roles.php");
    exit();
}

$id = intval($_GET['id']);

// Obtener los datos actuales del rol
$stmt = $conexion->prepare("SELECT nombre, descripcion FROM roles WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$rol = $result->fetch_assoc();

if (!$rol) {
    header("Location: roles.php");
    exit();
}

// Actualizar datos si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener valores anteriores para auditoría
    $nombre_anterior = $rol['nombre'];
    $descripcion_anterior = $rol['descripcion'];

    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];

    $stmt_update = $conexion->prepare("UPDATE roles SET nombre = ?, descripcion = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $nombre, $descripcion, $id);

    if ($stmt_update->execute()) {
        $mensaje = "Rol actualizado correctamente.";
        // Registrar éxito
        registrarAuditoria($conexion, $_SESSION["id_usuario"], $_SESSION["rol"], $usuario, 'EDITAR_ROL', 'EXITO', null, 'roles', $id, 'nombre', $nombre_anterior, $nombre);
        registrarAuditoria($conexion, $_SESSION["id_usuario"], $_SESSION["rol"], $usuario, 'EDITAR_ROL', 'EXITO', null, 'roles', $id, 'descripcion', $descripcion_anterior, $descripcion);
        // Actualizar valores locales para mostrar en el formulario
        $rol['nombre'] = $nombre;
        $rol['descripcion'] = $descripcion;
    } else {
        $mensaje = "Error al actualizar el rol: " . $conexion->error;
        // Registrar intento fallido
        registrarAuditoria($conexion, $_SESSION["id_usuario"], $_SESSION["rol"], $usuario, 'EDITAR_ROL', 'FALLIDO', $conexion->error, 'roles', $id, null, null, null);
    }
    $stmt_update->close();
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Rol - SISCOL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Editar Rol</h2>
    <p>Usuario: <strong><?php echo htmlspecialchars($usuario); ?></strong></p>
    <a href="roles.php" class="btn btn-secondary mb-3">⬅ Volver</a>

    <?php if($mensaje): ?>
        <div class="alert alert-info"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre del Rol</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required value="<?php echo htmlspecialchars($rol['nombre']); ?>">
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control"><?php echo htmlspecialchars($rol['descripcion']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar Rol</button>
    </form>
</div>
</body>
</html>