<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "director") {
    header("Location: index.php"); // Usa index.php si ese es tu login
    exit();
}

$conn = conectar();
$usuario = $_SESSION["usuario"];

// Obtener estadísticas usando las tablas reales
$total_estudiantes = $conn->query("SELECT COUNT(*) as total FROM estudiante WHERE activo = 1")->fetch_assoc()['total'] ?? 0;
$total_profesores = $conn->query("SELECT COUNT(*) as total FROM profesores WHERE activo = 1")->fetch_assoc()['total'] ?? 0;
$total_materias   = $conn->query("SELECT COUNT(*) as total FROM materias")->fetch_assoc()['total'] ?? 0;

// Total de usuarios = estudiantes + profesores + secretarios + directivos + super_usuario (solo activos)
$total_usuarios =
    ($conn->query("SELECT COUNT(*) as total FROM estudiante WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
    ($conn->query("SELECT COUNT(*) as total FROM profesores WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
    ($conn->query("SELECT COUNT(*) as total FROM secretarios WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
    ($conn->query("SELECT COUNT(*) as total FROM directivos WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
    ($conn->query("SELECT COUNT(*) as total FROM super_usuario WHERE activo = 1")->fetch_assoc()['total'] ?? 0);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SISCOL - Panel Director</title>
  <!-- Eliminado espacio al final en el enlace -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
    .header { background-color: #2c3e50; color: white; padding: 20px; margin-bottom: 30px; }
    .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #3498db; }
    .stat-label { color: #7f8c8d; font-size: 0.9rem; text-transform: uppercase; }
    .menu-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .menu-card h3 { color: #2c3e50; margin-bottom: 15px; }
    .menu-card ul { list-style: none; padding: 0; }
    .menu-card li { padding: 10px 0; border-bottom: 1px solid #ecf0f1; }
    .menu-card li:last-child { border-bottom: none; }
    .menu-card a { color: #3498db; text-decoration: none; }
    .menu-card a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="header">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1>Panel Director</h1>
          <p class="mb-0">Bienvenido, <?php echo htmlspecialchars($usuario); ?></p>
        </div>
        <a href="logout.php" class="btn btn-light">Cerrar Sesión</a>
      </div>
    </div>
  </div>

  <div class="container">
    <!-- Estadísticas -->
    <h2 class="mb-4">Resumen General</h2>
    <div class="row">
      <div class="col-md-3">
        <div class="stat-card text-center">
          <div class="stat-number"><?php echo $total_estudiantes; ?></div>
          <div class="stat-label">Estudiantes</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-card text-center">
          <div class="stat-number"><?php echo $total_profesores; ?></div>
          <div class="stat-label">Profesores</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-card text-center">
          <div class="stat-number"><?php echo $total_materias; ?></div>
          <div class="stat-label">Materias</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-card text-center">
          <div class="stat-number"><?php echo $total_usuarios; ?></div>
          <div class="stat-label">Total Usuarios</div>
        </div>
      </div>
    </div>

    <!-- Menús de acceso rápido -->
    <div class="row mt-4">
      <div class="col-md-6">
        <div class="menu-card">
          <h3>Gestión de Personal</h3>
          <ul>
            <li><a href="director/ver_profesores.php">Ver Profesores</a></li>
            <li><a href="director/ver_estudiantes.php">Ver Estudiantes</a></li>
            <li><a href="director/asignar_materias.php">Asignar Materias a Profesores</a></li>
          </ul>
        </div>
      </div>
      <div class="col-md-6">
        <div class="menu-card">
          <h3>Reportes y Estadísticas</h3>
          <ul>
            <li><a href="director/reporte_general.php">Reporte General</a></li>
            <li><a href="director/reporte_asistencias.php">Reporte de Asistencias</a></li>
            <li><a href="director/reporte_calificaciones.php">Reporte de Calificaciones</a></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-md-6">
        <div class="menu-card">
          <h3>Gestión Académica</h3>
          <ul>
            <li><a href="director/ver_materias.php">Ver Materias</a></li>
            <li><a href="director/gestionar_inscripciones.php">Gestionar Inscripciones</a></li>
          </ul>
        </div>
      </div>
      <div class="col-md-6">
        <div class="menu-card">
          <h3>Configuración</h3>
          <ul>
            <li><a href="configuracion.php">Configuración del Sistema</a></li>
            <li><a href="director/perfil.php">Mi Perfil</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</body>
</html>