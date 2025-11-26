<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "director") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();

$id_estudiante = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos del estudiante
$sql_estudiante = "SELECT u.*, r.nombre as rol_nombre
                   FROM usuarios u
                   JOIN roles r ON u.rol_id = r.id
                   WHERE u.id = ?";
$stmt = $conn->prepare($sql_estudiante);
$stmt->bind_param("i", $id_estudiante);
$stmt->execute();
$estudiante = $stmt->get_result()->fetch_assoc();

if (!$estudiante) {
    header("Location: ver_estudiantes.php");
    exit();
}

// Obtener materias inscritas
$sql_materias = "SELECT m.nombre, m.anio_lectivo, i.fecha_inscripcion
                 FROM inscripciones i
                 JOIN materias m ON i.id_materia = m.id
                 WHERE i.id_usuario = ?
                 ORDER BY i.fecha_inscripcion DESC";
$stmt_materias = $conn->prepare($sql_materias);
$stmt_materias->bind_param("i", $id_estudiante);
$stmt_materias->execute();
$materias = $stmt_materias->get_result();

// Obtener calificaciones
$sql_calificaciones = "SELECT m.nombre, c.nota, c.fecha
                       FROM calificaciones c
                       JOIN inscripciones i ON c.id_inscripcion = i.id
                       JOIN materias m ON i.id_materia = m.id
                       WHERE i.id_usuario = ?
                       ORDER BY c.fecha DESC
                       LIMIT 10";
$stmt_calif = $conn->prepare($sql_calificaciones);
$stmt_calif->bind_param("i", $id_estudiante);
$stmt_calif->execute();
$calificaciones = $stmt_calif->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalle Estudiante - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Detalle del Estudiante</h2>
      <a href="ver_estudiantes.php" class="btn btn-secondary">Volver</a>
    </div>

    <!-- Datos personales -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>Información Personal</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <p><strong>Nombre Completo:</strong> <?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']); ?></p>
            <p><strong>DNI:</strong> <?php echo htmlspecialchars($estudiante['dni']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($estudiante['email']); ?></p>
          </div>
          <div class="col-md-6">
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($estudiante['telefono']); ?></p>
            <p><strong>Estado:</strong> 
              <span class="badge bg-<?php echo $estudiante['activo'] ? 'success' : 'secondary'; ?>">
                <?php echo $estudiante['activo'] ? 'Activo' : 'Inactivo'; ?>
              </span>
            </p>
            <p><strong>Fecha de Registro:</strong> <?php echo date('d/m/Y', strtotime($estudiante['creado_en'])); ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Materias inscritas -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>Materias Inscritas</h5>
      </div>
      <div class="card-body">
        <?php if($materias->num_rows > 0): ?>
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Materia</th>
              <th>Año Lectivo</th>
              <th>Fecha Inscripción</th>
            </tr>
          </thead>
          <tbody>
            <?php while($mat = $materias->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($mat['nombre']); ?></td>
              <td><?php echo $mat['anio_lectivo']; ?></td>
              <td><?php echo date('d/m/Y', strtotime($mat['fecha_inscripcion'])); ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No tiene materias inscritas.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Calificaciones recientes -->
    <div class="card">
      <div class="card-header">
        <h5>Calificaciones Recientes</h5>
      </div>
      <div class="card-body">
        <?php if($calificaciones->num_rows > 0): ?>
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Materia</th>
              <th>Nota</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php while($calif = $calificaciones->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($calif['nombre']); ?></td>
              <td><span class="badge bg-primary"><?php echo number_format($calif['nota'], 2); ?></span></td>
              <td><?php echo date('d/m/Y', strtotime($calif['fecha'])); ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No tiene calificaciones registradas.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>