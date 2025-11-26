<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "director") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();

// Obtener todos los estudiantes con sus materias inscritas
$sql = "SELECT u.id, u.dni, u.nombre, u.apellido, u.email, u.telefono, u.activo,
        (SELECT COUNT(*) FROM inscripciones WHERE id_usuario = u.id) as total_materias
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE r.nombre = 'estudiante'
        ORDER BY u.apellido, u.nombre";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Estudiantes - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Lista de Estudiantes</h2>
      <a href="../panel_director.php" class="btn btn-secondary">Volver al Panel</a>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="mb-3">
          <input type="text" id="buscarEstudiante" class="form-control" placeholder="Buscar estudiante por nombre, apellido o DNI...">
        </div>
        <table class="table table-striped table-hover" id="tablaEstudiantes">
          <thead>
            <tr>
              <th>ID</th>
              <th>DNI</th>
              <th>Apellido y Nombre</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Materias</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr data-search="<?php echo strtolower($row['dni'] . ' ' . $row['nombre'] . ' ' . $row['apellido']); ?>">
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['dni']); ?></td>
              <td><?php echo htmlspecialchars($row['apellido'] . ', ' . $row['nombre']); ?></td>
              <td><?php echo htmlspecialchars($row['email']); ?></td>
              <td><?php echo htmlspecialchars($row['telefono']); ?></td>
              <td><?php echo $row['total_materias']; ?></td>
              <td>
                <span class="badge bg-<?php echo $row['activo'] ? 'success' : 'secondary'; ?>">
                  <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                </span>
              </td>
              <td>
                <a href="ver_detalle_estudiante.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Ver Detalle</a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  document.getElementById('buscarEstudiante').addEventListener('input', function() {
    const texto = this.value.toLowerCase();
    const filas = document.querySelectorAll('#tablaEstudiantes tbody tr');
    
    filas.forEach(fila => {
      const datos = fila.dataset.search || '';
      fila.style.display = datos.includes(texto) ? '' : 'none';
    });
  });
  </script>
</body>
</html>
<?php $conn->close(); ?>