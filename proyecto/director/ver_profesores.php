<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "director") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();

// Obtener todos los profesores
$sql = "SELECT u.id, u.dni, u.nombre, u.apellido, u.email, u.telefono, u.activo
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE r.nombre = 'profesor'
        ORDER BY u.apellido, u.nombre";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profesores - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Lista de Profesores</h2>
      <a href="../panel_director.php" class="btn btn-secondary">Volver al Panel</a>
    </div>

    <div class="card">
      <div class="card-body">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>DNI</th>
              <th>Apellido y Nombre</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['dni']); ?></td>
              <td><?php echo htmlspecialchars($row['apellido'] . ', ' . $row['nombre']); ?></td>
              <td><?php echo htmlspecialchars($row['email']); ?></td>
              <td><?php echo htmlspecialchars($row['telefono']); ?></td>
              <td>
                <span class="badge bg-<?php echo $row['activo'] ? 'success' : 'secondary'; ?>">
                  <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                </span>
              </td>
              <td>
                <a href="ver_materias_profesor.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Ver Materias</a>
              </td>
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