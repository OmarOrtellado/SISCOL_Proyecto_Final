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

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];

    $conn = conectar(); // Usar la función conectar()

    $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)");
    $stmt->bind_param("ss", $nombre, $descripcion);

    if ($stmt->execute()) {
        $nuevo_id = $conn->insert_id; // Obtener el ID del nuevo rol
        $mensaje = "Rol creado exitosamente con ID: " . $nuevo_id . ".";
        // Registrar éxito
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_ROL', 'EXITO', null, 'roles', $nuevo_id, 'nombre', null, $nombre);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_ROL', 'EXITO', null, 'roles', $nuevo_id, 'descripcion', null, $descripcion);
    } else {
        $mensaje = "Error: " . $stmt->error; // Usar $stmt->error en lugar de $conexion->error
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_ROL', 'FALLIDO', $stmt->error, 'roles', null, null, null, null);
    }
    $stmt->close();
    $conn->close(); // Cerrar la conexión
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Crear Rol - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h2>Crear Nuevo Rol</h2>
    <a href="roles.php" class="btn btn-secondary mb-3">⬅ Volver</a>

    <?php if($mensaje): ?>
      <div class="alert alert-info"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre del Rol</label>
        <input type="text" name="nombre" id="nombre" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="descripcion" class="form-label">Descripción</label>
        <textarea name="descripcion" id="descripcion" class="form-control"></textarea>
      </div>
      <button type="submit" class="btn btn-success">Crear Rol</button>
    </form>
  </div>
</body>
</html>