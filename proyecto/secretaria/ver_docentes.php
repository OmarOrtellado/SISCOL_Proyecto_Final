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

// Verificar permisos (ajusta según tu sistema de roles)
if (!isset($_SESSION["usuario"]) || !in_array($_SESSION["rol"], ["super_usuario", "director", "secretaria"])) {
    header("Location: index.php");
    exit();
}

$usuario = $_SESSION["usuario"];
$rol = $_SESSION["rol"];
$id_usuario_sesion = $_SESSION["id_usuario"];
$tipo_usuario_sesion = $_SESSION["rol"];

$conn = conectar();
if (!$conn) {
    die('<div class="alert alert-danger">Error de conexión.</div>');
}

$mensaje_exito = $error = null;

// ==============================
// PROCESAR ASIGNACIÓN DE MATERIA
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_materia') {
    $id_profesor = (int)($_POST['id_profesor'] ?? 0);
    $id_materia = (int)($_POST['id_materia'] ?? 0);
    $anio_lectivo = (int)($_POST['anio_lectivo'] ?? date('Y'));
    
    // Validar que el profesor existe
    $stmt_prof = $conn->prepare("SELECT id_profesor FROM profesores WHERE id_profesor = ? AND activo = 1");
    $stmt_prof->bind_param("i", $id_profesor);
    $stmt_prof->execute();
    if ($stmt_prof->get_result()->num_rows !== 1) {
        $error = "❌ El profesor seleccionado no es válido.";
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'FALLIDO', 'Profesor inválido', 'asignaciones', null, null, null, null);
    } else {
        // Validar que la materia existe
        $stmt_mat = $conn->prepare("SELECT id FROM materias WHERE id = ? AND activo = 1");
        $stmt_mat->bind_param("i", $id_materia);
        $stmt_mat->execute();
        if ($stmt_mat->get_result()->num_rows !== 1) {
            $error = "❌ La materia seleccionada no es válida.";
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'FALLIDO', 'Materia inválida', 'asignaciones', null, null, null, null);
        } else {
            // Intentar insertar la asignación
            $stmt_asig = $conn->prepare("
                INSERT INTO asignaciones (id_profesor, id_materia, anio_lectivo, activo) 
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE activo = 1
            ");
            $stmt_asig->bind_param("iii", $id_profesor, $id_materia, $anio_lectivo);
            
            if ($stmt_asig->execute()) {
                // Verificar si fue INSERT o UPDATE para determinar el id_objeto
                // Con ON DUPLICATE KEY UPDATE, si inserta, $conn->insert_id es el nuevo ID. Si actualiza, $conn->insert_id puede no cambiar.
                // Para simplificar, buscaremos el ID real de la asignación después de la operación.
                $stmt_buscar_id = $conn->prepare("SELECT id FROM asignaciones WHERE id_profesor = ? AND id_materia = ? AND anio_lectivo = ?");
                $stmt_buscar_id->bind_param("iii", $id_profesor, $id_materia, $anio_lectivo);
                $stmt_buscar_id->execute();
                $result_buscar = $stmt_buscar_id->get_result();
                $id_asignacion_registrada = null;
                if ($row_asig = $result_buscar->fetch_assoc()) {
                    $id_asignacion_registrada = (int)$row_asig['id'];
                }
                $stmt_buscar_id->close();

                $mensaje_exito = "✅ Materia asignada correctamente al docente.";
                // Registrar éxito
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'EXITO', null, 'asignaciones', $id_asignacion_registrada, 'id_profesor', null, $id_profesor);
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'EXITO', null, 'asignaciones', $id_asignacion_registrada, 'id_materia', null, $id_materia);
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'EXITO', null, 'asignaciones', $id_asignacion_registrada, 'anio_lectivo', null, $anio_lectivo);
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'EXITO', null, 'asignaciones', $id_asignacion_registrada, 'activo', null, 1);
            } else {
                $error = "❌ Error al asignar la materia: " . $conn->error;
                // Registrar intento fallido
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ASIGNAR_MATERIA', 'FALLIDO', $conn->error, 'asignaciones', null, null, null, null);
            }
            $stmt_asig->close();
        }
        $stmt_mat->close();
    }
    $stmt_prof->close();
}

// ==============================
// PROCESAR ELIMINACIÓN DE ASIGNACIÓN
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_asignacion') {
    $id_asignacion = (int)($_POST['id_asignacion'] ?? 0);
    
    // Obtener datos anteriores para auditoría
    $stmt_datos_anteriores = $conn->prepare("SELECT id_profesor, id_materia, anio_lectivo, activo FROM asignaciones WHERE id = ?");
    $stmt_datos_anteriores->bind_param("i", $id_asignacion);
    $stmt_datos_anteriores->execute();
    $result_datos = $stmt_datos_anteriores->get_result();
    $datos_anteriores = null;
    if ($row_datos = $result_datos->fetch_assoc()) {
        $datos_anteriores = $row_datos;
    }
    $stmt_datos_anteriores->close();

    if ($datos_anteriores) {
        $stmt_del = $conn->prepare("DELETE FROM asignaciones WHERE id = ?");
        $stmt_del->bind_param("i", $id_asignacion);
        
        if ($stmt_del->execute()) {
            $mensaje_exito = "✅ Asignación eliminada correctamente.";
            // Registrar éxito
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ELIMINAR_ASIGNACION', 'EXITO', null, 'asignaciones', $id_asignacion, 'activo', 1, null);
            // Opcional: Registrar también los datos de la asignación que se eliminó como contexto
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ELIMINAR_ASIGNACION', 'EXITO', null, 'asignaciones', $id_asignacion, 'id_profesor', null, $datos_anteriores['id_profesor']);
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ELIMINAR_ASIGNACION', 'EXITO', null, 'asignaciones', $id_asignacion, 'id_materia', null, $datos_anteriores['id_materia']);
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ELIMINAR_ASIGNACION', 'EXITO', null, 'asignaciones', $id_asignacion, 'anio_lectivo', null, $datos_anteriores['anio_lectivo']);
        } else {
            $error = "❌ Error al eliminar la asignación.";
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ELIMINAR_ASIGNACION', 'FALLIDO', $conn->error, 'asignaciones', $id_asignacion, null, null, null);
        }
        $stmt_del->close();
    } else {
        $error = "❌ No se encontró la asignación para eliminar.";
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ELIMINAR_ASIGNACION', 'FALLIDO', 'Asignación no encontrada', 'asignaciones', $id_asignacion, null, null, null);
    }
}

// ==============================
// OBTENER LISTA DE PROFESORES CON SUS ASIGNACIONES
// ==============================
$profesores = [];
$stmt = $conn->prepare("
    SELECT 
        p.id_profesor,
        p.nombre,
        p.apellido,
        p.dni,
        p.email,
        p.telefono,
        p.numero_legajo,
        p.activo
    FROM profesores p
    ORDER BY p.apellido, p.nombre
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $id_prof = $row['id_profesor'];
    
    // Obtener asignaciones del profesor
    $stmt_asig = $conn->prepare("
        SELECT 
            a.id,
            a.anio_lectivo,
            m.nombre AS materia,
            c.nombre AS carrera
        FROM asignaciones a
        INNER JOIN materias m ON a.id_materia = m.id
        INNER JOIN carreras c ON m.id_carrera = c.id_carrera
        WHERE a.id_profesor = ? AND a.activo = 1
        ORDER BY a.anio_lectivo DESC, m.nombre
    ");
    $stmt_asig->bind_param("i", $id_prof);
    $stmt_asig->execute();
    $asignaciones_result = $stmt_asig->get_result();
    
    $row['asignaciones'] = [];
    while ($asig = $asignaciones_result->fetch_assoc()) {
        $row['asignaciones'][] = $asig;
    }
    $stmt_asig->close();
    
    $profesores[] = $row;
}
$stmt->close();

// ==============================
// OBTENER MATERIAS DISPONIBLES
// ==============================
$materias_disponibles = [];
$stmt_mat = $conn->prepare("
    SELECT 
        m.id,
        m.nombre,
        m.año,
        c.nombre AS carrera
    FROM materias m
    INNER JOIN carreras c ON m.id_carrera = c.id_carrera
    WHERE m.activo = 1
    ORDER BY c.nombre, m.año, m.nombre
");
$stmt_mat->execute();
$result_mat = $stmt_mat->get_result();
while ($mat = $result_mat->fetch_assoc()) {
    $materias_disponibles[] = $mat;
}
$stmt_mat->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SISCOL - Gestión de Docentes</title>
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
    }
    
    .page-header h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    .page-header p {
      opacity: 0.9;
      margin: 0;
    }
    
    .card-docente {
      background: rgba(255,255,255,0.98);
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
      border: 1px solid rgba(255,255,255,0.2);
    }
    
    .card-docente:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }
    
    .docente-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f0f0f0;
    }
    
    .docente-info h4 {
      color: var(--azul-marino-500);
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    .docente-detalles {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      color: #666;
      font-size: 0.9rem;
    }
    
    .detalle-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .detalle-item strong {
      color: #333;
    }
    
    .badge-custom {
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
    }
    
    .badge-activo {
      background: #d4edda;
      color: #155724;
    }
    
    .badge-inactivo {
      background: #f8d7da;
      color: #721c24;
    }
    
    .asignaciones-container {
      margin-top: 1rem;
    }
    
    .asignaciones-title {
      color: var(--azul-marino-500);
      font-weight: 600;
      margin-bottom: 0.75rem;
      font-size: 1rem;
    }
    
    .asignacion-item {
      background: #f8f9fa;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      margin-bottom: 0.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-left: 4px solid var(--verde-olivo-500);
    }
    
    .asignacion-info {
      flex: 1;
    }
    
    .asignacion-materia {
      font-weight: 600;
      color: #333;
      margin-bottom: 0.25rem;
    }
    
    .asignacion-detalles {
      font-size: 0.85rem;
      color: #666;
    }
    
    .btn-eliminar-asig {
      background: #dc3545;
      color: white;
      border: none;
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .btn-eliminar-asig:hover {
      background: #c82333;
      transform: scale(1.05);
    }
    
    .btn-asignar {
      background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-sm);
    }
    
    .btn-asignar:hover {
      background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      color: white;
    }
    
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
    
    .modal-header h5 {
      font-weight: 700;
    }
    
    .modal-body {
      padding: 2rem;
    }
    
    .form-label {
      font-weight: 600;
      color: #333;
      margin-bottom: 0.5rem;
    }
    
    .form-control, .form-select {
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 0.75rem 1rem;
      transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--verde-olivo-500);
      box-shadow: 0 0 0 0.2rem rgba(85, 107, 47, 0.15);
    }
    
    .alert {
      border-radius: 12px;
      border: none;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
      font-weight: 500;
    }
    
    .alert-success {
      background: linear-gradient(135deg, #d4edda, #c3e6cb);
      color: #155724;
    }
    
    .alert-danger {
      background: linear-gradient(135deg, #f8d7da, #f5c6cb);
      color: #721c24;
    }
    
    .alert-info {
      background: linear-gradient(135deg, #d1ecf1, #bee5eb);
      color: #0c5460;
    }
    
    .sin-asignaciones {
      text-align: center;
      padding: 2rem;
      color: #999;
      font-style: italic;
    }
    
    .stats-bar {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .stat-box {
      flex: 1;
      background: rgba(255,255,255,0.98);
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: var(--shadow-sm);
      border-left: 4px solid var(--verde-olivo-500);
    }
    
    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--azul-marino-500);
      margin-bottom: 0.5rem;
    }
    
    .stat-label {
      color: #666;
      font-weight: 500;
      font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
      .docente-header {
        flex-direction: column;
        gap: 1rem;
      }
      
      .stats-bar {
        flex-direction: column;
      }
      
      .asignacion-item {
        flex-direction: column;
        gap: 0.5rem;
        align-items: start;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar topbar">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">👨‍🏫 SISCOL • Gestión de Docentes</a>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($usuario) ?></span>
        <a href="javascript:history.back()" class="back-btn">← Volver</a>
        <a href="logout.php" class="logout-btn">Cerrar sesión</a>
      </div>
    </div>
  </nav>

  <div class="container-custom">
    <?php if ($mensaje_exito): ?>
      <div class="alert alert-success">
        <strong>✓</strong> <?= htmlspecialchars($mensaje_exito) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger">
        <strong>✗</strong> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <h2>📋 Gestión de Docentes</h2>
      <p>Administra los profesores y sus asignaciones de materias</p>
    </div>

    <div class="stats-bar">
      <div class="stat-box">
        <div class="stat-number"><?= count($profesores) ?></div>
        <div class="stat-label">Total de Docentes</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">
          <?= count(array_filter($profesores, fn($p) => $p['activo'] == 1)) ?>
        </div>
        <div class="stat-label">Docentes Activos</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">
          <?php 
            $total_asignaciones = 0;
            foreach ($profesores as $prof) {
              $total_asignaciones += count($prof['asignaciones']);
            }
            echo $total_asignaciones;
          ?>
        </div>
        <div class="stat-label">Asignaciones Totales</div>
      </div>
    </div>

    <?php if (empty($profesores)): ?>
      <div class="alert alert-info">
        📌 <strong>No hay docentes registrados.</strong> Agrega docentes al sistema para poder asignarles materias.
      </div>
    <?php else: ?>
      <?php foreach ($profesores as $docente): ?>
        <div class="card-docente">
          <div class="docente-header">
            <div class="docente-info">
              <h4>
                👨‍🏫 <?= htmlspecialchars($docente['apellido'] . ', ' . $docente['nombre']) ?>
                <?php if ($docente['activo']): ?>
                  <span class="badge-custom badge-activo">Activo</span>
                <?php else: ?>
                  <span class="badge-custom badge-inactivo">Inactivo</span>
                <?php endif; ?>
              </h4>
              <div class="docente-detalles">
                <div class="detalle-item">
                  <span>📋</span>
                  <strong>DNI:</strong> <?= htmlspecialchars($docente['dni']) ?>
                </div>
                <div class="detalle-item">
                  <span>📧</span>
                  <strong>Email:</strong> <?= htmlspecialchars($docente['email']) ?>
                </div>
                <?php if ($docente['telefono']): ?>
                <div class="detalle-item">
                  <span>📱</span>
                  <strong>Teléfono:</strong> <?= htmlspecialchars($docente['telefono']) ?>
                </div>
                <?php endif; ?>
                <?php if ($docente['numero_legajo']): ?>
                <div class="detalle-item">
                  <span>🔢</span>
                  <strong>Legajo:</strong> <?= htmlspecialchars($docente['numero_legajo']) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <button class="btn btn-asignar" onclick="abrirModalAsignacion(<?= $docente['id_profesor'] ?>, '<?= htmlspecialchars($docente['apellido'] . ', ' . $docente['nombre']) ?>')">
                ➕ Asignar Materia
              </button>
            </div>
          </div>

          <div class="asignaciones-container">
            <div class="asignaciones-title">📚 Materias Asignadas:</div>
            <?php if (empty($docente['asignaciones'])): ?>
              <div class="sin-asignaciones">
                Sin asignaciones de materias
              </div>
            <?php else: ?>
              <?php foreach ($docente['asignaciones'] as $asig): ?>
                <div class="asignacion-item">
                  <div class="asignacion-info">
                    <div class="asignacion-materia">
                      📖 <?= htmlspecialchars($asig['materia']) ?>
                    </div>
                    <div class="asignacion-detalles">
                      🎓 <?= htmlspecialchars($asig['carrera']) ?> • 
                      📅 Año Lectivo: <?= htmlspecialchars($asig['anio_lectivo']) ?>
                    </div>
                  </div>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta asignación?');">
                    <input type="hidden" name="accion" value="eliminar_asignacion">
                    <input type="hidden" name="id_asignacion" value="<?= $asig['id'] ?>">
                    <button type="submit" class="btn-eliminar-asig">🗑️ Eliminar</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Modal para Asignar Materia -->
  <div class="modal fade" id="modalAsignarMateria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">➕ Asignar Materia a Docente</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="accion" value="asignar_materia">
            <input type="hidden" name="id_profesor" id="modal_id_profesor">
            
            <div class="mb-3">
              <label class="form-label">👨‍🏫 Docente:</label>
              <input type="text" class="form-control" id="modal_nombre_profesor" readonly>
            </div>
            
            <div class="mb-3">
              <label class="form-label">📚 Materia: *</label>
              <select name="id_materia" class="form-select" required>
                <option value="">-- Selecciona una materia --</option>
                <?php 
                  $carrera_actual = '';
                  foreach ($materias_disponibles as $mat): 
                    if ($carrera_actual !== $mat['carrera']) {
                      if ($carrera_actual !== '') echo '</optgroup>';
                      $carrera_actual = $mat['carrera'];
                      echo '<optgroup label="' . htmlspecialchars($carrera_actual) . '">';
                    }
                ?>
                  <option value="<?= $mat['id'] ?>">
                    <?= htmlspecialchars($mat['nombre']) ?> (<?= $mat['año'] ?>° Año)
                  </option>
                <?php 
                  endforeach; 
                  if ($carrera_actual !== '') echo '</optgroup>';
                ?>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label">📅 Año Lectivo: *</label>
              <input type="number" name="anio_lectivo" class="form-control" 
                     value="<?= date('Y') ?>" min="2020" max="2030" required>
            </div>
            
            <div class="alert alert-info">
              💡 <strong>Nota:</strong> Si la materia ya está asignada al docente para este año, se reactivará la asignación.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-asignar">💾 Guardar Asignación</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-ocultar alertas
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert-success, .alert-danger');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.transition = 'opacity 0.5s ease';
          alert.style.opacity = '0';
          setTimeout(() => alert.remove(), 500);
        }, 5000);
      });
    });

    // Abrir modal de asignación
    function abrirModalAsignacion(idProfesor, nombreProfesor) {
      document.getElementById('modal_id_profesor').value = idProfesor;
      document.getElementById('modal_nombre_profesor').value = nombreProfesor;
      
      const modal = new bootstrap.Modal(document.getElementById('modalAsignarMateria'));
      modal.show();
    }
  </script>
</body>
</html>