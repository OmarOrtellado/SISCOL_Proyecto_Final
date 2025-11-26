<?php
session_start();
require_once "conexion.php";

// Verificar permisos del director
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "director") {
    header("Location: index.php");
    exit();
}

$usuario = $_SESSION["usuario"];
$conn = null;
$mensaje_error = null;

try {
    $conn = conectar();
    if (!$conn) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    // Obtener estadísticas generales
    $total_estudiantes = $conn->query("SELECT COUNT(*) as total FROM estudiante WHERE activo = 1")->fetch_assoc()['total'] ?? 0;
    $total_profesores = $conn->query("SELECT COUNT(*) as total FROM profesores WHERE activo = 1")->fetch_assoc()['total'] ?? 0;
    $total_materias = $conn->query("SELECT COUNT(*) as total FROM materias")->fetch_assoc()['total'] ?? 0;

    $total_usuarios =
        ($conn->query("SELECT COUNT(*) as total FROM estudiante WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
        ($conn->query("SELECT COUNT(*) as total FROM profesores WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
        ($conn->query("SELECT COUNT(*) as total FROM secretarios WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
        ($conn->query("SELECT COUNT(*) as total FROM directivos WHERE activo = 1")->fetch_assoc()['total'] ?? 0) +
        ($conn->query("SELECT COUNT(*) as total FROM super_usuario WHERE activo = 1")->fetch_assoc()['total'] ?? 0);

    // === Datos para el Modal de Profesores ===
    $profesores_con_materias = [];
    $stmt_prof = $conn->prepare("
        SELECT p.id_profesor, p.nombre, p.apellido, p.dni, p.email,
               COALESCE(GROUP_CONCAT(m.nombre ORDER BY m.año, m.nombre SEPARATOR ', '), 'Sin materias asignadas') as materias_asignadas
        FROM profesores p
        LEFT JOIN asignaciones a ON p.id_profesor = a.id_profesor AND a.activo = 1
        LEFT JOIN materias m ON a.id_materia = m.id
        WHERE p.activo = 1
        GROUP BY p.id_profesor, p.nombre, p.apellido, p.dni, p.email
        ORDER BY p.apellido, p.nombre
    ");
    $stmt_prof->execute();
    $profesores_con_materias = $stmt_prof->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_prof->close();

    // === Datos para el Modal de Estudiantes ===
    $estudiantes_detalle = [];
    $stmt_est = $conn->prepare("
        SELECT e.id, e.nombre, e.apellido, e.dni,
               COALESCE(c.nombre, 'Sin carrera asignada') as carrera,
               (SELECT COUNT(*) FROM analitico an WHERE an.id_estudiante = e.id AND an.cursada = 1) as total_materias_cursadas,
               (SELECT COUNT(*) FROM analitico an WHERE an.id_estudiante = e.id AND an.regular = 1) as total_regular,
               (SELECT COUNT(*) FROM analitico an WHERE an.id_estudiante = e.id AND an.aprobada = 1) as total_aprobadas
        FROM estudiante e
        LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
        WHERE e.activo = 1
        ORDER BY e.apellido, e.nombre
    ");
    $stmt_est->execute();
    $estudiantes_detalle = $stmt_est->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_est->close();

    // === Datos para el Modal de Carreras (y Materias) ===
    $carreras_con_materias = [];
    $stmt_carr = $conn->prepare("
        SELECT c.id_carrera, c.nombre as nombre_carrera, c.duracion,
               m.id as id_materia, m.nombre as nombre_materia, m.año
        FROM carreras c
        LEFT JOIN materias m ON c.id_carrera = m.id_carrera AND m.activo = 1
        WHERE c.estado = 'activa'
        ORDER BY c.nombre, m.año, m.nombre
    ");
    $stmt_carr->execute();
    $result_carr = $stmt_carr->get_result();
    $carreras_temp = [];
    while ($row = $result_carr->fetch_assoc()) {
        $id_carrera = $row['id_carrera'];
        if (!isset($carreras_temp[$id_carrera])) {
            $carreras_temp[$id_carrera] = [
                'id_carrera' => $id_carrera,
                'nombre_carrera' => $row['nombre_carrera'],
                'duracion' => $row['duracion'],
                'materias' => []
            ];
        }
        if ($row['id_materia']) {
            $carreras_temp[$id_carrera]['materias'][] = [
                'id_materia' => $row['id_materia'],
                'nombre_materia' => $row['nombre_materia'],
                'año' => $row['año']
            ];
        }
    }
    $carreras_con_materias = array_values($carreras_temp);
    $stmt_carr->close();

    // === Datos para el Modal de Reporte de Asistencias ===
    // Calcula el promedio de asistencia por carrera
    $stmt_asist = $conn->prepare("
        SELECT c.nombre as carrera, 
               COALESCE(ROUND(AVG(a.presente) * 100, 2), 0) as promedio_asistencia
        FROM carreras c
        LEFT JOIN materias m ON c.id_carrera = m.id_carrera
        LEFT JOIN asistencias a ON m.id = a.id_materia
        WHERE c.estado = 'activa'
        GROUP BY c.id_carrera, c.nombre
        ORDER BY c.nombre
    ");
    $stmt_asist->execute();
    $reporte_asistencias = $stmt_asist->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_asist->close();

    // === Datos para el Modal de Reporte de Calificaciones ===
    // Calcula el promedio de calificaciones por carrera
    $stmt_calif = $conn->prepare("
        SELECT c.nombre as carrera, 
               COALESCE(ROUND(AVG(an.calificacion_final), 2), 0) as promedio_calificacion
        FROM carreras c
        LEFT JOIN materias m ON c.id_carrera = m.id_carrera
        LEFT JOIN analitico an ON m.id = an.id_materia AND an.aprobada = 1
        WHERE c.estado = 'activa'
        GROUP BY c.id_carrera, c.nombre
        ORDER BY c.nombre
    ");
    $stmt_calif->execute();
    $reporte_calificaciones = $stmt_calif->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_calif->close();

} catch (Exception $e) {
    $mensaje_error = "Error crítico: " . $e->getMessage();
    if ($conn) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SISCOL - Panel Director</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
        --azul-marino-500: #1E3A5F;
        --azul-marino-600: #1a3354;
        --azul-marino-700: #162b45;
        --verde-olivo-500: #556B2F;
        --verde-olivo-600: #4a5e29;
        --verde-olivo-700: #3f5023;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.1);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.15);
        --shadow-lg: 0 8px 32px rgba(0,0,0,0.2);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body { 
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: linear-gradient(135deg, #0d1a28 0%, #162b45 50%, #1a3354 100%);
        color: white; 
        min-height: 100vh;
    }
    
    .topbar { 
        background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
        padding: 1rem 1.5rem;
        box-shadow: var(--shadow-lg);
        border-bottom: 3px solid var(--verde-olivo-700);
    }
    
    .navbar-brand {
        font-weight: 700;
        font-size: 1.5rem;
        letter-spacing: 1px;
        color: white !important;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .user-name {
        font-weight: 500;
        padding: 0.5rem 1rem;
        background: rgba(255,255,255,0.15);
        border-radius: 8px;
        backdrop-filter: blur(10px);
    }
    
    .back-btn, .logout-btn {
        color: white;
        text-decoration: none;
        padding: 0.5rem 1.25rem;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .back-btn:hover, .logout-btn:hover {
        background: rgba(255,255,255,0.2);
        color: white;
        transform: translateY(-2px);
    }
    
    .container-custom {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
    
    .page-header {
        background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
        color: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        text-align: center;
    }
    
    .page-header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .page-header p {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    .stats-bar {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-box {
        flex: 1;
        background: rgba(255,255,255,0.98);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        text-align: center;
        transition: all 0.3s ease;
        border-left: 4px solid var(--verde-olivo-500);
    }
    
    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }
    
    .stat-number {
        font-size: 3rem;
        font-weight: 700;
        color: var(--azul-marino-500);
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        color: #666;
        font-weight: 600;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .menu-section {
        margin-bottom: 3rem;
    }
    
    .menu-header {
        background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
        color: white !important;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        font-weight: 600;
        margin-bottom: 0;
        text-align: center;
    }
    
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .menu-card {
        background: rgba(255,255,255,0.98);
        border-radius: 0 0 16px 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .menu-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .menu-card h3 {
        color: var(--azul-marino-500);
        margin-bottom: 1rem;
        font-weight: 600;
        border-bottom: 2px solid var(--verde-olivo-500);
        padding-bottom: 0.5rem;
    }
    
    .menu-card ul {
        list-style: none;
        padding: 0;
    }
    
    .menu-card li {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .menu-card li:last-child {
        border-bottom: none;
    }
    
    .menu-card a {
        color: var(--azul-marino-500);
        text-decoration: none;
        display: block;
        padding: 0.5rem 0;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .menu-card a:hover {
        color: var(--verde-olivo-500);
        padding-left: 0.5rem;
        text-decoration: none;
    }
    
    /* Estilos para Modales */
    .modal-content {
        border-radius: 16px;
        border: none;
        overflow: hidden;
    }
    
    .modal-header {
        background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
        color: white;
        border: none;
        padding: 1.5rem;
    }
    
    .modal-body {
        padding: 2rem;
    }
    
    .search-container {
        margin-bottom: 20px;
        position: relative;
    }
    
    .search-container input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
    }
    
    .table-container {
        overflow-x: auto;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    th {
        background: #f8f9fa;
        color: #333;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e0e0e0;
    }
    
    td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        color: #333;
    }
    
    tr:hover {
        background: #f8f9fa;
    }
    
    .alert-error {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        font-weight: 500;
        border-left: 4px solid #dc3545;
    }
    
    @media (max-width: 768px) {
        .stats-bar {
            flex-direction: column;
        }
        
        .menu-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header h1 {
            font-size: 2rem;
        }
    }
  </style>
</head>
<body>
  <nav class="navbar topbar">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">SISCOL • Panel Director</a>
      <div class="user-info">
        <span class="user-name">👋 <?= htmlspecialchars($usuario) ?></span>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
      </div>
    </div>
  </nav>

  <div class="container-custom">
    <div class="page-header">
      <h1>📊 Dashboard del Director</h1>
      <p>Resumen general y acceso rápido a la gestión académica</p>
    </div>

    <?php if ($mensaje_error): ?>
        <div class="alert-error">
            <strong>⚠️ Error de Sistema:</strong> <?= htmlspecialchars($mensaje_error) ?>
        </div>
    <?php else: ?>
        <div class="stats-bar">
            <div class="stat-box">
                <div class="stat-number"><?= $total_estudiantes ?></div>
                <div class="stat-label">Estudiantes Activos</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $total_profesores ?></div>
                <div class="stat-label">Profesores Activos</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $total_materias ?></div>
                <div class="stat-label">Materias Totales</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $total_usuarios ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
        </div>

        <div class="menu-section">
            <h2 class="text-center mb-4" style="color: white; font-weight: 600;">Menú de Gestión</h2>
            <div class="menu-grid">
                <div class="menu-card">
                    <h3 class="menu-header">👥 Gestión de Personal</h3>
                    <ul>
                        <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalVerProfesores">Ver Profesores</a></li>
                        <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalVerEstudiantes">Ver Estudiantes</a></li>
                    </ul>
                </div>

                <div class="menu-card">
                    <h3 class="menu-header">🎓 Gestión Académica</h3>
                    <ul>
                        <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalVerCarreras">Ver Carreras</a></li>
                    </ul>
                </div>

                <div class="menu-card">
                    <h3 class="menu-header">📈 Reportes y Estadísticas</h3>
                    <ul>
                        <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalReporteAsistencias">Reporte de Asistencias</a></li>
                        <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalReporteCalificaciones">Reporte de Calificaciones</a></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
  </div>

  <!-- Modal: Ver Profesores -->
  <div class="modal fade" id="modalVerProfesores" tabindex="-1" aria-labelledby="modalVerProfesoresLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalVerProfesoresLabel">Ver Profesores</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="search-container">
            <input type="text" id="searchProfesores" placeholder="Buscar por nombre, apellido o DNI...">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <div class="table-container">
            <?php if (!empty($profesores_con_materias)): ?>
                <table id="tableProfesores">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>DNI</th>
                            <th>Email</th>
                            <th>Materias Asignadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profesores_con_materias as $prof): ?>
                            <tr>
                                <td><?= htmlspecialchars($prof['apellido'] . ', ' . $prof['nombre']) ?></td>
                                <td><?= htmlspecialchars($prof['dni']) ?></td>
                                <td><?= htmlspecialchars($prof['email']) ?></td>
                                <td><?= htmlspecialchars($prof['materias_asignadas']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center text-muted">No hay profesores activos registrados.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Ver Estudiantes -->
  <div class="modal fade" id="modalVerEstudiantes" tabindex="-1" aria-labelledby="modalVerEstudiantesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalVerEstudiantesLabel">Ver Estudiantes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="search-container">
            <input type="text" id="searchEstudiantes" placeholder="Buscar por nombre, apellido o DNI...">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <div class="table-container">
            <?php if (!empty($estudiantes_detalle)): ?>
                <table id="tableEstudiantes">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>DNI</th>
                            <th>Carrera</th>
                            <th>Cursando</th>
                            <th>Regular</th>
                            <th>Aprobadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes_detalle as $est): ?>
                            <tr>
                                <td><?= htmlspecialchars($est['apellido'] . ', ' . $est['nombre']) ?></td>
                                <td><?= htmlspecialchars($est['dni']) ?></td>
                                <td><?= htmlspecialchars($est['carrera']) ?></td>
                                <td><?= htmlspecialchars($est['total_materias_cursadas']) ?></td>
                                <td><?= htmlspecialchars($est['total_regular']) ?></td>
                                <td><?= htmlspecialchars($est['total_aprobadas']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center text-muted">No hay estudiantes activos registrados.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Ver Carreras -->
  <div class="modal fade" id="modalVerCarreras" tabindex="-1" aria-labelledby="modalVerCarrerasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalVerCarrerasLabel">Ver Carreras</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="search-container">
            <input type="text" id="searchCarreras" placeholder="Buscar por nombre de carrera...">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <div class="table-container">
            <?php if (!empty($carreras_con_materias)): ?>
                <?php foreach ($carreras_con_materias as $carrera): ?>
                    <h6 class="mt-4" style="color: var(--azul-marino-500);"><?= htmlspecialchars($carrera['nombre_carrera']) ?> (<?= $carrera['duracion'] ?> años)</h6>
                    <?php if (!empty($carrera['materias'])): ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Año</th>
                                    <th>Nombre de la Materia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carrera['materias'] as $materia): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($materia['año']) ?>°</td>
                                        <td><?= htmlspecialchars($materia['nombre_materia']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">Esta carrera no tiene materias asignadas.</p>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">No hay carreras activas registradas.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Reporte de Asistencias -->
  <div class="modal fade" id="modalReporteAsistencias" tabindex="-1" aria-labelledby="modalReporteAsistenciasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalReporteAsistenciasLabel">Reporte de Asistencias</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="search-container">
            <input type="text" id="searchAsistencias" placeholder="Buscar por nombre de carrera...">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <div class="table-container">
            <?php if (!empty($reporte_asistencias)): ?>
                <table id="tableAsistencias">
                    <thead>
                        <tr>
                            <th>Carrera</th>
                            <th>Promedio de Asistencia (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_asistencias as $rep): ?>
                            <tr>
                                <td><?= htmlspecialchars($rep['carrera']) ?></td>
                                <td><?= htmlspecialchars($rep['promedio_asistencia']) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center text-muted">No hay datos de asistencia disponibles.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Reporte de Calificaciones -->
  <div class="modal fade" id="modalReporteCalificaciones" tabindex="-1" aria-labelledby="modalReporteCalificacionesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalReporteCalificacionesLabel">Reporte de Calificaciones</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="search-container">
            <input type="text" id="searchCalificaciones" placeholder="Buscar por nombre de carrera...">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <div class="table-container">
            <?php if (!empty($reporte_calificaciones)): ?>
                <table id="tableCalificaciones">
                    <thead>
                        <tr>
                            <th>Carrera</th>
                            <th>Promedio de Calificación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_calificaciones as $rep): ?>
                            <tr>
                                <td><?= htmlspecialchars($rep['carrera']) ?></td>
                                <td><?= htmlspecialchars($rep['promedio_calificacion']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center text-muted">No hay calificaciones aprobadas registradas.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Función genérica para filtrar tablas
    function setupTableFilter(inputId, tableId, columnsToSearch) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        const table = document.getElementById(tableId);
        if (!table) return;

        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let shouldShow = false;
                for (let j of columnsToSearch) {
                    const cell = rows[i].getElementsByTagName('td')[j];
                    if (cell && cell.textContent.toLowerCase().indexOf(filter) > -1) {
                        shouldShow = true;
                        break;
                    }
                }
                rows[i].style.display = shouldShow ? '' : 'none';
            }
        });
    }

    // Configurar los filtros para cada tabla
    document.addEventListener('DOMContentLoaded', function() {
        setupTableFilter('searchProfesores', 'tableProfesores', [0, 1, 2]); // Nombre, DNI, Email
        setupTableFilter('searchEstudiantes', 'tableEstudiantes', [0, 1, 2]); // Nombre, DNI, Carrera
        setupTableFilter('searchAsistencias', 'tableAsistencias', [0]); // Carrera
        setupTableFilter('searchCalificaciones', 'tableCalificaciones', [0]); // Carrera
        
        // El filtro para carreras es más complejo (tablas anidadas), se omite por simplicidad en este ejemplo.
        // Se podría implementar si es crítico.
    });
  </script>
</body>
</html>