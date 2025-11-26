<?php
session_start();
require_once "../conexion.php";

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

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "secretaria") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();
$mensaje = "";

// Procesar creación de materia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_materia'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $anio_lectivo = intval($_POST['anio_lectivo']);
    
    if (!empty($nombre) && $anio_lectivo > 2000) {
        $sql = "INSERT INTO materias (nombre, descripcion, anio_lectivo, activo) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nombre, $descripcion, $anio_lectivo);
        
        if ($stmt->execute()) {
            $nuevo_id = $conn->insert_id;
            $mensaje = "<div class='alert alert-success'>Materia creada correctamente con ID: $nuevo_id.</div>";
            // Registrar éxito
            $usuario_nombre_sesion = $_SESSION["usuario"];
            $id_usuario_sesion = $_SESSION["id_usuario"];
            $tipo_usuario_sesion = $_SESSION["rol"]; // "secretaria"
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'nombre', null, $nombre);
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'descripcion', null, $descripcion);
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'anio_lectivo', null, $anio_lectivo);
            // El campo 'activo' se establece en 1 por defecto, también se puede registrar si se desea
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'activo', null, 1);
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al crear la materia: " . $stmt->error . "</div>";
            // Registrar intento fallido
            $usuario_nombre_sesion = $_SESSION["usuario"];
            $id_usuario_sesion = $_SESSION["id_usuario"];
            $tipo_usuario_sesion = $_SESSION["rol"]; // "secretaria"
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', $stmt->error, 'materias', null, null, null, null);
        }
    } else {
        $mensaje = "<div class='alert alert-warning'>Por favor complete todos los campos correctamente.</div>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "secretaria"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', 'Campos incompletos o inválidos', 'materias', null, null, null, null);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear Materia - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Crear Nueva Materia</h2>
      <a href="../panel_secretaria.php" class="btn btn-secondary">Volver al Panel</a>
    </div>

    <?php echo $mensaje; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label for="nombre" class="form-label">Nombre de la Materia</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="100">
          </div>

          <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="255"></textarea>
            <small class="text-muted">Opcional - Máximo 255 caracteres</small>
          </div>

          <div class="mb-3">
            <label for="anio_lectivo" class="form-label">Año Lectivo</label>
            <input type="number" name="anio_lectivo" id="anio_lectivo" class="form-control" 
                   value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
          </div>

          <button type="submit" name="crear_materia" class="btn btn-primary">Crear Materia</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>