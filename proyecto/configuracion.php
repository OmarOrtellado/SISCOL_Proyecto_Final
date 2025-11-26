<?php
session_start();
require_once "conexion.php";

// Crear la conexión
$conn = conectar();

// Verificar que solo el super_usuario pueda acceder
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

// Cargar configuración actual
$sql = "SELECT * FROM configuracion_sistema LIMIT 1";
$result = $conn->query($sql);
$config = $result ? $result->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>SISCOL - Configuración</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h2 class="mb-4">⚙️ Configuración del Sistema</h2>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'ok'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
    ⚡ Configuración guardada correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php endif; ?>

  
  <?php if ($config): ?>
  <form action="guardar_configuracion.php" method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Nombre de la Institución</label>
      <input type="text" name="nombre_institucion" class="form-control" 
             value="<?php echo htmlspecialchars($config['nombre_institucion']); ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Dirección</label>
      <input type="text" name="direccion" class="form-control" 
             value="<?php echo htmlspecialchars($config['direccion']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Teléfono</label>
      <input type="text" name="telefono" class="form-control" 
             value="<?php echo htmlspecialchars($config['telefono']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" 
             value="<?php echo htmlspecialchars($config['email']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Lema</label>
      <input type="text" name="lema" class="form-control" 
             value="<?php echo htmlspecialchars($config['lema']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Año Lectivo</label>
      <input type="text" name="anio_lectivo" class="form-control" 
             value="<?php echo htmlspecialchars($config['anio_lectivo']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Logo (opcional)</label>
      <input type="file" name="logo" class="form-control">
      <?php if (!empty($config['logo'])): ?>
        <div class="mt-2">
          <img src="uploads/<?php echo htmlspecialchars($config['logo']); ?>" alt="Logo actual" height="60">
        </div>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-success">💾 Guardar Cambios</button>
    <a href="panel_admin.php" class="btn btn-secondary">⬅ Volver</a>
  </form>
  <?php else: ?>
    <div class="alert alert-danger">⚠️ No se encontró configuración en la base de datos.</div>
  <?php endif; ?>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</html>
<?php $conn->close(); ?>