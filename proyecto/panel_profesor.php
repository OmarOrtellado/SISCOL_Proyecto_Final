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
            "issssssssssssss",
            $id_usuario,
            $tipo_usuario,
            $usuario_nombre,
            $accion,
            $resultado,
            $motivo,
            $objeto_afectado,
            $id_objeto,
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

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] !== "profesor") {
    header("Location: index.php");
    exit();
}

$conn = conectar();
if (!$conn) {
    die('<div class="alert alert-danger">Error de conexión a la base de datos.</div>');
}

$usuario = $_SESSION["usuario"];
$id_profesor = (int)$_SESSION["id_usuario"];
$tipo_usuario = $_SESSION["rol"]; // "profesor"

// Registrar el acceso al panel de profesor
registrarAuditoria($conn, $id_profesor, $tipo_usuario, $usuario, 'ACCESO_PANEL', 'EXITO', null, 'panel_profesor', null);

// 1. Materias asignadas al profesor
$total_materias = 0;
$stmt1 = $conn->prepare("
    SELECT COUNT(DISTINCT id_materia) as total 
    FROM asignaciones 
    WHERE id_profesor = ? AND activo = 1
");
$stmt1->bind_param("i", $id_profesor);
$stmt1->execute();
$result1 = $stmt1->get_result();
if ($row = $result1->fetch_assoc()) {
    $total_materias = $row['total'];
}
$stmt1->close();

// 2. Total de estudiantes en sus materias
$total_estudiantes = 0;
$stmt2 = $conn->prepare("
    SELECT COUNT(DISTINCT a.id_estudiante) as total
    FROM analitico a
    INNER JOIN asignaciones asig ON a.id_materia = asig.id_materia
    WHERE asig.id_profesor = ? AND a.activo = 1
");
$stmt2->bind_param("i", $id_profesor);
$stmt2->execute();
$result2 = $stmt2->get_result();
if ($row = $result2->fetch_assoc()) {
    $total_estudiantes = $row['total'];
}
$stmt2->close();

// 3. Calificaciones pendientes (ejemplo)
$calificaciones_pendientes = 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SISCOL - Panel Profesor</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --azul-marino: #1a365d;
      --azul-marino-claro: #2c5282;
      --verde-olivo: #556B2F;
      --verde-olivo-claro: #6b8e23;
      --blanco: #ffffff;
      --negro: #1a1a1a;
      --gris-claro: #f7fafc;
      --gris-medio: #e2e8f0;
      --sombra: rgba(0, 0, 0, 0.1);
    }

    body { 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, var(--azul-marino) 0%, var(--azul-marino-claro) 50%, var(--verde-olivo) 100%);
      min-height: 100vh;
      padding: 0;
      margin: 0;
    }

    .header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .header-content {
      max-width: 1400px;
      margin: 0 auto;
      padding: 25px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .header-info h1 {
      color: var(--azul-marino);
      font-size: 28px;
      margin-bottom: 5px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .header-info p {
      color: var(--verde-olivo);
      font-size: 16px;
      font-weight: 500;
    }

    .btn-logout {
      padding: 12px 28px;
      background: var(--verde-olivo);
      color: var(--blanco);
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 15px;
      font-weight: 600;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-logout:hover {
      background: var(--verde-olivo-claro);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(85, 107, 47, 0.3);
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 40px 30px;
    }

    .section-title {
      color: var(--blanco);
      font-size: 24px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .stat-card {
      background: var(--blanco);
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--azul-marino), var(--verde-olivo));
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
      background: linear-gradient(135deg, var(--azul-marino), var(--azul-marino-claro));
    }

    .stat-card:nth-child(2) .stat-icon {
      background: linear-gradient(135deg, var(--verde-olivo), var(--verde-olivo-claro));
    }

    .stat-card:nth-child(3) .stat-icon {
      background: linear-gradient(135deg, var(--azul-marino-claro), var(--verde-olivo));
    }

    .stat-number {
      font-size: 42px;
      font-weight: 700;
      color: var(--azul-marino);
      margin-bottom: 8px;
      line-height: 1;
    }

    .stat-label {
      color: var(--verde-olivo);
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 25px;
    }

    .menu-card {
      background: var(--blanco);
      border-radius: 15px;
      padding: 0;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;
      overflow: hidden;
    }

    .menu-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
    }

    .menu-header {
      background: linear-gradient(135deg, var(--azul-marino), var(--azul-marino-claro));
      color: var(--blanco);
      padding: 20px 25px;
      font-size: 18px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .menu-card:nth-child(2) .menu-header {
      background: linear-gradient(135deg, var(--verde-olivo), var(--verde-olivo-claro));
    }

    .menu-card:nth-child(3) .menu-header {
      background: linear-gradient(135deg, var(--azul-marino-claro), var(--verde-olivo));
    }

    .menu-body {
      padding: 25px;
    }

    .menu-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .menu-item {
      border-bottom: 1px solid var(--gris-medio);
      transition: all 0.3s ease;
    }

    .menu-item:last-child {
      border-bottom: none;
    }

    .menu-item:hover {
      background: var(--gris-claro);
      padding-left: 10px;
    }

    .menu-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 15px 0;
      color: var(--negro);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .menu-link:hover {
      color: var(--azul-marino);
    }

    .menu-link svg {
      flex-shrink: 0;
      color: var(--verde-olivo);
    }

    .welcome-banner {
      background: linear-gradient(135deg, rgba(26, 54, 93, 0.95), rgba(85, 107, 47, 0.95));
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      color: var(--blanco);
    }

    .welcome-banner h2 {
      font-size: 28px;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .welcome-banner p {
      font-size: 16px;
      opacity: 0.9;
    }

    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        align-items: flex-start;
      }

      .stats-grid,
      .menu-grid {
        grid-template-columns: 1fr;
      }

      .container {
        padding: 20px 15px;
      }

      .section-title {
        font-size: 20px;
      }

      .stat-number {
        font-size: 36px;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-content">
      <div class="header-info">
        <h1>
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
          </svg>
          SISCOL - Panel Profesor
        </h1>
        <p>👨‍🏫 Bienvenido, <?php echo htmlspecialchars($usuario); ?></p>
      </div>
      <a href="logout.php" class="btn-logout">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        Cerrar Sesión
      </a>
    </div>
  </div>

  <div class="container">
    <!-- Banner de bienvenida -->
    <div class="welcome-banner">
      <h2>
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        ¡Hola, Profesor <?php echo htmlspecialchars($usuario); ?>!
      </h2>
      <p>Gestiona tus materias, estudiantes y calificaciones desde este panel de control</p>
    </div>

    <!-- Estadísticas -->
    <h2 class="section-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="20" x2="18" y2="10"></line>
        <line x1="12" y1="20" x2="12" y2="4"></line>
        <line x1="6" y1="20" x2="6" y2="14"></line>
      </svg>
      Resumen de Actividades
    </h2>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
        </div>
        <div class="stat-number"><?php echo $total_materias; ?></div>
        <div class="stat-label">Materias Asignadas</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
        </div>
        <div class="stat-number"><?php echo $total_estudiantes; ?></div>
        <div class="stat-label">Total Estudiantes</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
        </div>
        <div class="stat-number"><?php echo $calificaciones_pendientes; ?></div>
        <div class="stat-label">Calificaciones Pendientes</div>
      </div>
    </div>

    <!-- Menús de acceso -->
    <h2 class="section-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7"></rect>
        <rect x="14" y="3" width="7" height="7"></rect>
        <rect x="14" y="14" width="7" height="7"></rect>
        <rect x="3" y="14" width="7" height="7"></rect>
      </svg>
      Acceso Rápido
    </h2>
    <div class="menu-grid">
      <div class="menu-card">
        <div class="menu-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
          Mis Materias
        </div>
        <div class="menu-body">
          <ul class="menu-list">
            <li class="menu-item">
              <a href="profesor/mis_materias.php" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Ver Mis Materias
              </a>
            </li>
            <li class="menu-item">
              <a href="profesor/lista_estudiantes.php" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Ver Estudiantes por Materia
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="menu-card">
        <div class="menu-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          Calificaciones
        </div>
        <div class="menu-body">
          <ul class="menu-list">
            <li class="menu-item">
              <a href="profesor/cargar_calificaciones.php" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M18.36 6.59L12 12.95l-5.73-5.73A1 1 0 0 0 5 8.64V19a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8.64a1 1 0 0 0-1.24-1.41z"></path>
                  <path d="M12 2v3.5"></path>
                </svg>
                Cargar y Ver Calificaciones
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="menu-card">
        <div class="menu-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          Asistencias
        </div>
        <div class="menu-body">
          <ul class="menu-list">
            <li class="menu-item">
              <a href="profesor/tomar_asistencia.php" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="9 11 12 14 22 4"></polyline>
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
                Tomar Asistencia
              </a>
            </li>
            <li class="menu-item">
              <a href="profesor/ver_asistencias.php" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                Ver Registro de Asistencias
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</body>
</html>