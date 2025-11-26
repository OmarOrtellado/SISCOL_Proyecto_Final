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

$id_usuario_sesion = $_SESSION["id_usuario"];
$tipo_usuario_sesion = $_SESSION["rol"]; // "secretaria"
$usuario_nombre_sesion = $_SESSION["usuario"];

$conn = conectar();
$mensaje = "";

// Procesar inscripción
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inscribir'])) {
    $id_usuario = intval($_POST['id_usuario']);
    $id_materia = intval($_POST['id_materia']);
    
    // Verificar si ya está inscrito
    $check = $conn->prepare("SELECT id FROM inscripciones WHERE id_usuario = ? AND id_materia = ?");
    $check->bind_param("ii", $id_usuario, $id_materia);
    $check->execute();
    $result = $check->get_result();
    
    if($result->num_rows > 0) {
        $mensaje = "<div class='alert alert-warning'>El estudiante ya está inscrito en esta materia.</div>";
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'INSCRIBIR_MATERIA', 'FALLIDO', 'Estudiante ya inscrito', 'inscripciones', null, null, null, null);
    } else {
        $sql = "INSERT INTO inscripciones (id_usuario, id_materia) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id_usuario, $id_materia);
        
        if ($stmt->execute()) {
            $nuevo_id_inscripcion = $conn->insert_id;
            $mensaje = "<div class='alert alert-success'>Inscripción realizada correctamente.</div>";
            // Registrar éxito
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'INSCRIBIR_MATERIA', 'EXITO', null, 'inscripciones', $nuevo_id_inscripcion, 'id_usuario', null, $id_usuario);
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'INSCRIBIR_MATERIA', 'EXITO', null, 'inscripciones', $nuevo_id_inscripcion, 'id_materia', null, $id_materia);
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al realizar la inscripción.</div>";
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'INSCRIBIR_MATERIA', 'FALLIDO', $stmt->error, 'inscripciones', null, null, null, null);
        }
        $stmt->close();
    }
    $check->close();
}

// Obtener lista de estudiantes
$sql_estudiantes = "SELECT u.id, u.dni, u.nombre, u.apellido 
                    FROM usuarios u
                    JOIN roles r ON u.rol_id = r.id
                    WHERE r.nombre = 'estudiante' AND u.activo = 1
                    ORDER BY u.apellido, u.nombre";
$estudiantes = $conn->query($sql_estudiantes);

// Obtener lista de materias activas
$sql_materias = "SELECT id, nombre, anio_lectivo FROM materias WHERE activo = 1 ORDER BY nombre";
$materias = $conn->query($sql_materias);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscribir Estudiante - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Inscribir Estudiante a Materia</h2>
      <a href="../panel_secretaria.php" class="btn btn-secondary">Volver al Panel</a>
    </div>

    <?php echo $mensaje; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label for="id_usuario" class="form-label">Estudiante</label>
            <select name="id_usuario" id="id_usuario" class="form-control" required>
              <option value="">-- Seleccione un estudiante --</option>
              <?php while($est = $estudiantes->fetch_assoc()): ?>
              <option value="<?php echo $est['id']; ?>">
                <?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre'] . ' (DNI: ' . $est['dni'] . ')'); ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="id_materia" class="form-label">Materia</label>
            <select name="id_materia" id="id_materia" class="form-control" required>
              <option value="">-- Seleccione una materia --</option>
              <?php while($mat = $materias->fetch_assoc()): ?>
              <option value="<?php echo $mat['id']; ?>">
                <?php echo htmlspecialchars($mat['nombre'] . ' (' . $mat['anio_lectivo'] . ')'); ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>

          <button type="submit" name="inscribir" class="btn btn-success">Inscribir</button>
        </form>
      </div>
    </div>

    <!-- Mostrar inscripciones recientes -->
    <div class="card mt-4">
      <div class="card-body">
        <h5>Inscripciones Recientes</h5>
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Estudiante</th>
              <th>Materia</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql_recientes = "SELECT u.nombre, u.apellido, m.nombre as materia, i.fecha_inscripcion
                             FROM inscripciones i
                             JOIN usuarios u ON i.id_usuario = u.id
                             JOIN materias m ON i.id_materia = m.id
                             ORDER BY i.fecha_inscripcion DESC
                             LIMIT 10";
            $recientes = $conn->query($sql_recientes);
            while($rec = $recientes->fetch_assoc()):
            ?>
            <tr>
              <td><?php echo htmlspecialchars($rec['apellido'] . ', ' . $rec['nombre']); ?></td>
              <td><?php echo htmlspecialchars($rec['materia']); ?></td>
              <td><?php echo date('d/m/Y H:i', strtotime($rec['fecha_inscripcion'])); ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>