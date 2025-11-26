<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "profesor") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();
$mensaje = "";

// Procesar asistencia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_asistencia'])) {
    $id_materia = intval($_POST['id_materia']);
    $fecha = $_POST['fecha'];
    $estados = isset($_POST['estado']) ? $_POST['estado'] : array();
    
    // Verificar que la materia esté asignada al profesor
    $check_asignacion = $conn->prepare("SELECT 1 FROM asignaciones WHERE id_profesor = ? AND id_materia = ? AND activo = 1");
    $check_asignacion->bind_param("ii", $_SESSION["id_usuario"], $id_materia);
    $check_asignacion->execute();
    $asignacion_result = $check_asignacion->get_result();
    
    if ($asignacion_result->num_rows == 0) {
        $mensaje = "<div class='alert alert-danger'>Error: No tienes permiso para tomar asistencia en esta materia.</div>";
    } else {
        foreach($estados as $id_estudiante => $estado) {
            // Validar el estado recibido
            $presente_val = 0;
            $ausente_val = 0;
            if ($estado === 'presente') {
                $presente_val = 1;
                $ausente_val = 0; // Asegura que ausente sea 0 si está presente
            } elseif ($estado === 'ausente') {
                $presente_val = 0; // Asegura que presente sea 0 si está ausente
                $ausente_val = 1;
            }
            // Si $estado es 'no_marcado' o cualquier otro valor inesperado, ambos permanecen 0
            
            // Verificar si ya existe registro para esta fecha
            $check = $conn->prepare("SELECT id FROM asistencias WHERE id_usuario = ? AND id_materia = ? AND fecha = ?");
            $check->bind_param("iis", $id_estudiante, $id_materia, $fecha);
            $check->execute();
            $result = $check->get_result();
            
            if($result->num_rows > 0) {
                // Actualizar
                $update = $conn->prepare("UPDATE asistencias SET presente = ?, ausente = ? WHERE id_usuario = ? AND id_materia = ? AND fecha = ?");
                $update->bind_param("iiiis", $presente_val, $ausente_val, $id_estudiante, $id_materia, $fecha);
                $update->execute();
            } else {
                // Insertar
                $insert = $conn->prepare("INSERT INTO asistencias (id_usuario, id_materia, fecha, presente, ausente) VALUES (?, ?, ?, ?, ?)");
                $insert->bind_param("iisii", $id_estudiante, $id_materia, $fecha, $presente_val, $ausente_val);
                $insert->execute();
            }
        }
        
        $mensaje = "<div class='alert alert-success'>Asistencia guardada correctamente.</div>";
    }
}

// Obtener materia seleccionada
$id_materia = isset($_GET['materia']) ? intval($_GET['materia']) : (isset($_POST['id_materia']) ? intval($_POST['id_materia']) : 0);
$fecha_seleccionada = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');

// Obtener materias asignadas al profesor
$sql_materias = "SELECT m.id, m.nombre 
                 FROM asignaciones a
                 JOIN materias m ON a.id_materia = m.id
                 WHERE a.id_profesor = ? AND a.activo = 1 AND m.activo = 1
                 ORDER BY m.nombre";
$stmt_materias = $conn->prepare($sql_materias);
$stmt_materias->bind_param("i", $_SESSION["id_usuario"]);
$stmt_materias->execute();
$materias = $stmt_materias->get_result();

// Obtener estudiantes si hay materia seleccionada
$estudiantes = null;
if ($id_materia > 0) {
    // Verificar que la materia esté asignada al profesor
    $check_asignacion = $conn->prepare("SELECT 1 FROM asignaciones WHERE id_profesor = ? AND id_materia = ? AND activo = 1");
    $check_asignacion->bind_param("ii", $_SESSION["id_usuario"], $id_materia);
    $check_asignacion->execute();
    $asignacion_result = $check_asignacion->get_result();
    
    if ($asignacion_result->num_rows == 0) {
        $mensaje = "<div class='alert alert-danger'>Error: No tienes permiso para ver esta materia.</div>";
    } else {
        // Obtener estudiantes inscritos vía tabla 'analitico' y su estado de asistencia
        $sql_estudiantes = "SELECT e.id, e.dni, e.nombre, e.apellido,
                            (SELECT presente FROM asistencias WHERE id_usuario = e.id AND id_materia = ? AND fecha = ?) as presente_hoy,
                            (SELECT ausente FROM asistencias WHERE id_usuario = e.id AND id_materia = ? AND fecha = ?) as ausente_hoy
                            FROM analitico a
                            JOIN estudiante e ON a.id_estudiante = e.id
                            WHERE a.id_materia = ? AND a.activo = 1 AND e.activo = 1
                            ORDER BY e.apellido, e.nombre";
        $stmt = $conn->prepare($sql_estudiantes);
        $stmt->bind_param("isiii", $id_materia, $fecha_seleccionada, $id_materia, $fecha_seleccionada, $id_materia);
        $stmt->execute();
        $estudiantes = $stmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tomar Asistencia - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .estado-checkbox { transform: scale(1.2); cursor: pointer; margin-right: 0.5rem; }
    body {
      background-color: #f8f9fa;
    }
    .card {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      border: 1px solid rgba(0, 0, 0, 0.125);
    }
    .table th {
      border-top: 0;
    }
    .acciones-cell {
        white-space: nowrap;
    }
  </style>
  <script>
    // Función para manejar la exclusión de checkboxes
    function toggleEstado(checkbox) {
        const row = checkbox.closest('tr');
        const id_estudiante = checkbox.name.replace('estado[', '').replace(']', '');
        const presenteBox = row.querySelector(`input[name='estado[${id_estudiante}]'][value='presente']`);
        const ausenteBox = row.querySelector(`input[name='estado[${id_estudiante}]'][value='ausente']`);

        if (checkbox.value === 'presente' && checkbox.checked) {
            ausenteBox.checked = false;
        } else if (checkbox.value === 'ausente' && checkbox.checked) {
            presenteBox.checked = false;
        }
        // Si se desmarca uno, el otro no se toca, permitiendo dejar ambos sin marcar.
    }
  </script>
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h3">Tomar Asistencia</h2>
      <a href="../panel_profesor.php" class="btn btn-outline-secondary">Volver al Panel</a>
    </div>

    <?php echo $mensaje; ?>

    <!-- Selector de materia y fecha -->
    <div class="card mb-4">
      <div class="card-body">
        <h5>Seleccionar Materia y Fecha</h5>
        <form method="GET">
          <div class="row">
            <div class="col-md-6">
              <label>Materia</label>
              <select name="materia" class="form-control" required onchange="this.form.submit()">
                <option value="">-- Seleccione una materia --</option>
                <?php 
                $materias->data_seek(0);
                while($mat = $materias->fetch_assoc()): 
                ?>
                <option value="<?php echo $mat['id']; ?>" <?php echo ($id_materia == $mat['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($mat['nombre']); ?>
                </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Formulario de asistencia -->
    <?php if($estudiantes && $estudiantes->num_rows > 0): ?>
    <form method="POST">
      <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
      
      <div class="card mb-3">
        <div class="card-body">
          <label>Fecha</label>
          <input type="date" name="fecha" class="form-control" value="<?php echo $fecha_seleccionada; ?>" required>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">Lista de Estudiantes</h5>
          <table class="table table-striped">
            <thead>
              <tr>
                <th>DNI</th>
                <th>Apellido y Nombre</th>
                <th class="text-center acciones-cell">Presente</th>
                <th class="text-center acciones-cell">Ausente</th>
              </tr>
            </thead>
            <tbody>
              <?php while($est = $estudiantes->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($est['dni']); ?></td>
                <td><?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?></td>
                <td class="text-center">
                  <input type="checkbox" name="estado[<?php echo $est['id']; ?>]" value="presente" 
                         class="estado-checkbox" 
                         onchange="toggleEstado(this)"
                         <?php echo ($est['presente_hoy'] == 1) ? 'checked' : ''; ?>>
                </td>
                <td class="text-center">
                  <input type="checkbox" name="estado[<?php echo $est['id']; ?>]" value="ausente" 
                         class="estado-checkbox" 
                         onchange="toggleEstado(this)"
                         <?php echo ($est['ausente_hoy'] == 1) ? 'checked' : ''; ?>>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <div class="text-end mt-3">
            <button type="submit" name="guardar_asistencia" class="btn btn-success btn-lg">Guardar Asistencia</button>
          </div>
        </div>
      </div>
    </form>
    <?php elseif($id_materia > 0 && empty($mensaje)): ?>
    <div class="alert alert-info" role="alert">
        No hay estudiantes inscritos en esta materia o no tienes permiso para verla.
    </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
$stmt_materias->close();
if (isset($check_asignacion)) $check_asignacion->close();
if (isset($stmt)) $stmt->close();
$conn->close(); 
?>