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

// Verificar permisos (secretaria, director o super_usuario)
if (!isset($_SESSION["usuario"]) || !in_array($_SESSION["rol"], ["secretaria", "director", "super_usuario"])) {
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
// PROCESAR ACTUALIZACIÓN DE AÑO ACADÉMICO
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_anio') {
    $id_estudiante = (int)($_POST['id_estudiante'] ?? 0);
    $anio_actual = $_POST['anio_actual'] ?? null;
    $motivo = trim($_POST['motivo'] ?? '');

    // Si anio_actual es vacío, se pone NULL para cálculo automático
    if ($anio_actual === '' || $anio_actual === 'auto') {
        $anio_actual = null;
    } else {
        $anio_actual = (int)$anio_actual;
        // Validar que sea un año válido (1-6)
        if ($anio_actual < 1 || $anio_actual > 6) {
            $error = "❌ El año debe estar entre 1 y 6.";
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ACTUALIZAR_ANIO_ACADEMICO', 'FALLIDO', 'Año inválido', 'estudiante', $id_estudiante, 'anio_actual', null, $anio_actual);
        }
    }

    if (!$error) {
        // Obtener valor anterior para auditoría
        $stmt_anterior = $conn->prepare("SELECT anio_actual FROM estudiante WHERE id = ?");
        $stmt_anterior->bind_param("i", $id_estudiante);
        $stmt_anterior->execute();
        $result_anterior = $stmt_anterior->get_result();
        $row_anterior = $result_anterior->fetch_assoc();
        $valor_anterior = $row_anterior['anio_actual'];
        $stmt_anterior->close();

        // Actualizar año académico
        if ($anio_actual === null) {
            $stmt = $conn->prepare("UPDATE estudiante SET anio_actual = NULL WHERE id = ?");
            $stmt->bind_param("i", $id_estudiante);
        } else {
            $stmt = $conn->prepare("UPDATE estudiante SET anio_actual = ? WHERE id = ?");
            $stmt->bind_param("ii", $anio_actual, $id_estudiante);
        }

        if ($stmt->execute()) {
            $tipo_cambio = ($anio_actual === null) ? "cálculo automático" : "{$anio_actual}° año";
            $mensaje_exito = "✅ Año académico actualizado a {$tipo_cambio}.";
            // Registrar éxito
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ACTUALIZAR_ANIO_ACADEMICO', 'EXITO', null, 'estudiante', $id_estudiante, 'anio_actual', $valor_anterior, $anio_actual);
            // Registrar también el motivo si se proporcionó
            if (!empty($motivo)) {
                // Se podría crear un campo específico para motivo en la tabla de auditoría si es crítico
                // Por ahora, se registra como parte del motivo_fallo o como un evento separado si es necesario
            }
        } else {
            $error = "❌ Error al actualizar: " . $conn->error;
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ACTUALIZAR_ANIO_ACADEMICO', 'FALLIDO', $conn->error, 'estudiante', $id_estudiante, 'anio_actual', $valor_anterior, $anio_actual);
        }
        $stmt->close();
    }
}

// ==============================
// PROCESAR ACTUALIZACIÓN DE AÑO DE INGRESO
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_ingreso') {
    $id_estudiante = (int)($_POST['id_estudiante'] ?? 0);
    $anio_ingreso = (int)($_POST['anio_ingreso'] ?? date('Y'));

    // Validar año de ingreso
    if ($anio_ingreso < 2000 || $anio_ingreso > (date('Y') + 1)) {
        $error = "❌ El año de ingreso no es válido.";
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ACTUALIZAR_ANIO_INGRESO', 'FALLIDO', 'Año de ingreso inválido', 'estudiante', $id_estudiante, 'anio_ingreso', null, $anio_ingreso);
    } else {
        // Obtener valor anterior para auditoría
        $stmt_anterior = $conn->prepare("SELECT anio_ingreso FROM estudiante WHERE id = ?");
        $stmt_anterior->bind_param("i", $id_estudiante);
        $stmt_anterior->execute();
        $result_anterior = $stmt_anterior->get_result();
        $row_anterior = $result_anterior->fetch_assoc();
        $valor_anterior = $row_anterior['anio_ingreso'];
        $stmt_anterior->close();

        $stmt = $conn->prepare("UPDATE estudiante SET anio_ingreso = ? WHERE id = ?");
        $stmt->bind_param("ii", $anio_ingreso, $id_estudiante);

        if ($stmt->execute()) {
            $mensaje_exito = "✅ Año de ingreso actualizado correctamente.";
            // Registrar éxito
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ACTUALIZAR_ANIO_INGRESO', 'EXITO', null, 'estudiante', $id_estudiante, 'anio_ingreso', $valor_anterior, $anio_ingreso);
        } else {
            $error = "❌ Error al actualizar: " . $conn->error;
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario, 'ACTUALIZAR_ANIO_INGRESO', 'FALLIDO', $conn->error, 'estudiante', $id_estudiante, 'anio_ingreso', $valor_anterior, $anio_ingreso);
        }
        $stmt->close();
    }
}

// ==============================
// OBTENER LISTA DE ESTUDIANTES
// ==============================
$estudiantes = [];
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.dni,
        e.nombre,
        e.apellido,
        e.email,
        e.id_carrera,
        e.anio_ingreso,
        e.anio_actual,
        e.activo,
        c.nombre AS carrera,
        c.duracion AS duracion_carrera,
        CASE 
            WHEN e.anio_actual IS NOT NULL THEN e.anio_actual
            WHEN e.anio_ingreso IS NOT NULL THEN 
                LEAST(GREATEST((YEAR(CURDATE()) - e.anio_ingreso + 1), 1), COALESCE(c.duracion, 6))
            ELSE 1
        END AS anio_calculado
    FROM estudiante e
    LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
    ORDER BY e.apellido, e.nombre
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Calcular estadísticas del estudiante
    $id_est = $row['id'];
    // Contar materias cursando
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_cursando,
            SUM(CASE WHEN regular = 1 THEN 1 ELSE 0 END) as total_regular,
            SUM(CASE WHEN aprobada = 1 THEN 1 ELSE 0 END) as total_aprobada
        FROM analitico
        WHERE id_estudiante = ? AND cursada = 1
    ");
    $stats_stmt->bind_param("i", $id_est);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    $stats_stmt->close();

    $row['stats'] = $stats;
    $estudiantes[] = $row;
}
$stmt->close();

// ==============================
// LÓGICA PARA ANALÍTICO (INTEGRADA)
// ==============================
$analitico_data = null;
if (isset($_GET['action']) && $_GET['action'] === 'ver_analitico' && isset($_GET['id_est'])) {
    $id_estudiante_seleccionado = (int)$_GET['id_est'];
    // Verificar que el estudiante exista y esté activo
    $verif = $conn->prepare("
        SELECT 
            e.nombre, e.apellido, e.dni, e.email, e.anio_ingreso, e.anio_actual,
            c.nombre AS carrera, c.duracion AS duracion_carrera,
            CASE 
                WHEN e.anio_actual IS NOT NULL THEN e.anio_actual
                WHEN e.anio_ingreso IS NOT NULL THEN 
                    LEAST(GREATEST((YEAR(CURDATE()) - e.anio_ingreso + 1), 1), COALESCE(c.duracion, 6))
                ELSE 1
            END AS anio_calculado
        FROM estudiante e
        LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
        WHERE e.id = ? AND e.activo = 1
    ");
    $verif->bind_param("i", $id_estudiante_seleccionado);
    $verif->execute();
    $verif_result = $verif->get_result()->fetch_assoc();
    $verif->close();

    if ($verif_result) {
        $nombre_estudiante = $verif_result['nombre'] . ' ' . $verif_result['apellido'];
        $duracion = $verif_result['duracion_carrera'] ?? 4;
        $progreso = round(($verif_result['anio_calculado'] / $duracion) * 100);

        // Obtener analítico completo con asistencia calculada desde asistencias
        $analitico_stmt = $conn->prepare("
            SELECT 
                m.nombre AS materia,
                m.año,
                a.cursada,
                a.regular,
                a.aprobada,
                a.calificacion_final,
                (SELECT COALESCE(ROUND(AVG(presente) * 100, 0), 0) FROM asistencias ast WHERE ast.id_usuario = ? AND ast.id_materia = a.id_materia) AS asistencia,
                c.nombre AS carrera
            FROM analitico a
            INNER JOIN materias m ON a.id_materia = m.id
            INNER JOIN carreras c ON m.id_carrera = c.id_carrera
            WHERE a.id_estudiante = ?
            ORDER BY c.id_carrera, m.año, m.nombre
        ");
        $analitico_stmt->bind_param("ii", $id_estudiante_seleccionado, $id_estudiante_seleccionado);
        $analitico_stmt->execute();
        $analitico = $analitico_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $analitico_stmt->close();

        // Calcular estadísticas generales
        $total_materias = count($analitico);
        $total_aprobadas = count(array_filter($analitico, fn($m) => $m['aprobada'] == 1));
        $total_regular = count(array_filter($analitico, fn($m) => $m['regular'] == 1));
        $total_cursando = count(array_filter($analitico, fn($m) => $m['cursada'] == 1));
        $porc_aprobadas = $total_materias > 0 ? round(($total_aprobadas / $total_materias) * 100) : 0;
        $porc_asistencia_prom = $total_materias > 0 ? round(array_sum(array_column($analitico, 'asistencia')) / $total_materias) : 0;

        $analitico_data = [
            'estudiante' => [
                'nombre_completo' => $nombre_estudiante,
                'dni' => $verif_result['dni'],
                'email' => $verif_result['email'],
                'carrera' => $verif_result['carrera'],
                'anio_ingreso' => $verif_result['anio_ingreso'] ?? 'No definido',
                'anio_actual' => $verif_result['anio_calculado'] . '° año',
                'progreso_carrera' => $progreso . '%',
                'modo_anio' => $verif_result['anio_actual'] !== null ? 'Manual' : 'Automático'
            ],
            'estadisticas' => [
                'total_materias' => $total_materias,
                'aprobadas' => $total_aprobadas,
                'regular' => $total_regular,
                'cursando' => $total_cursando,
                'porc_aprobadas' => $porc_aprobadas,
                'porc_asistencia_prom' => $porc_asistencia_prom
            ],
            'data' => $analitico
        ];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SISCOL - Gestión de Estudiantes</title>
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
      max-width: 1600px;
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
    .card-estudiante {
      background: rgba(255,255,255,0.98);
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
      border: 1px solid rgba(255,255,255,0.2);
    }
    .card-estudiante:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
    }
    .estudiante-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f0f0f0;
    }
    .estudiante-info h4 {
      color: var(--azul-marino-500);
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .estudiante-detalles {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      color: #666;
      font-size: 0.9rem;
      margin-top: 1rem;
    }
    .detalle-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .detalle-item strong {
      color: #333;
    }
    .anio-section {
      background: #f8f9fa;
      padding: 1.5rem;
      border-radius: 12px;
      margin-top: 1rem;
      border-left: 4px solid var(--verde-olivo-500);
    }
    .anio-info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .anio-box {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      box-shadow: var(--shadow-sm);
    }
    .anio-label {
      font-size: 0.85rem;
      color: #666;
      font-weight: 600;
      text-transform: uppercase;
      margin-bottom: 0.5rem;
    }
    .anio-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--azul-marino-500);
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
    .badge-auto {
      background: #d1ecf1;
      color: #0c5460;
    }
    .badge-manual {
      background: #fff3cd;
      color: #856404;
    }
    .btn-editar {
      background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
      color: white;
      border: none;
      padding: 0.6rem 1.25rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-sm);
      cursor: pointer;
    }
    .btn-editar:hover {
      background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      color: white;
    }
    .btn-analitico {
      background: linear-gradient(135deg, #0d6efd, #0b5ed7);
      color: white;
      border: none;
      padding: 0.6rem 1.25rem;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-sm);
      cursor: pointer;
    }
    .btn-analitico:hover {
      background: linear-gradient(135deg, #0b5ed7, #0a58ca);
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
    .stats-estudiante {
      display: flex;
      gap: 1rem;
      margin-top: 0.5rem;
      flex-wrap: wrap;
    }
    .stat-mini {
      background: white;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      box-shadow: var(--shadow-sm);
    }
    .stat-mini strong {
      color: var(--azul-marino-500);
      font-weight: 700;
    }
    /* Estilos para badges de analítico */
    .estado-aprobada { background: #d4edda; color: #155724; }
    .estado-regular { background: #fff3cd; color: #856404; }
    .estado-cursando { background: #d1ecf1; color: #0c5460; }
    .estado-sin-estado { background: #f8d7da; color: #721c24; }
    /* Estilos para Analítico PDF-like */
    .analitico-header {
      background: white;
      padding: 2rem;
      border-radius: 8px 8px 0 0;
      text-align: center;
      margin-bottom: 0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .analitico-header h2 {
      color: var(--azul-marino-500);
      font-size: 1.8rem;
      margin-bottom: 1rem;
    }
    .analitico-info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .info-item {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 6px;
      border-left: 4px solid var(--verde-olivo-500);
    }
    .info-label {
      font-weight: 600;
      color: #666;
      display: block;
    }
    .info-value {
      font-size: 1.1rem;
      color: var(--azul-marino-500);
      font-weight: 700;
    }
    .estadisticas-generales {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-general {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-general-label {
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }
    .stat-general-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--azul-marino-500);
    }
    .carrera-section {
      page-break-inside: avoid;
      margin-bottom: 2rem;
    }
    .carrera-header {
      background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
      color: white;
      padding: 1rem;
      border-radius: 6px 6px 0 0;
      font-weight: 600;
      margin-bottom: 0;
    }
    .analitico-table {
      font-family: 'Courier New', monospace;
      font-size: 0.9rem;
      width: 100%;
      border-collapse: collapse;
    }
    .analitico-table th,
    .analitico-table td {
      border: 1px solid #ddd;
      padding: 0.5rem;
      text-align: left;
    }
    .analitico-table th {
      background: #f8f9fa;
      font-weight: 600;
      color: #333;
    }
    .analitico-table .text-center {
      text-align: center;
    }
    /* Estilos para impresión */
    @media print {
      @page {
        size: A4 portrait;
        margin: 0.5cm;
      }
      body * {
        visibility: hidden;
      }
      #modalAnalitico .modal-content,
      #modalAnalitico .modal-content * {
        visibility: visible;
      }
      #modalAnalitico .modal-content {
        position: static !important;
        left: auto !important;
        top: auto !important;
        width: 100% !important;
        height: auto !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .modal-header,
      .modal-footer {
        display: none !important;
      }
      .modal-body {
        padding: 0 !important;
        margin: 0 !important;
      }
      #analiticoContent {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
      }
      .analitico-header {
        padding: 0.5rem !important;
        margin: 0 !important;
        break-inside: avoid !important;
        page-break-after: avoid !important;
      }
      .analitico-header h2 {
        margin: 0 !important;
        padding: 0 !important;
      }
      .analitico-info {
        margin-bottom: 1rem !important;
        gap: 0.5rem !important;
      }
      .info-item {
        margin-bottom: 0.5rem !important;
        padding: 0.5rem !important;
      }
      .estadisticas-generales {
        margin-bottom: 1rem !important;
        gap: 0.5rem !important;
      }
      .stat-general {
        padding: 0.5rem !important;
        margin-bottom: 0.5rem !important;
      }
      .stat-general-value {
        font-size: 1rem !important;
      }
      .analitico-table {
        font-size: 7pt !important;
        margin-bottom: 0.5rem !important;
      }
      .carrera-section {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        margin-bottom: 0.5rem !important;
      }
      .carrera-header {
        padding: 0.5rem !important;
        margin-bottom: 0 !important;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar topbar">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">👥 SISCOL • Gestión de Estudiantes</a>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($usuario) ?> (<?= htmlspecialchars(ucfirst($rol)) ?>)</span>
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
      <h2>📊 Gestión de Estudiantes y Años Académicos</h2>
      <p>Administra el año académico de cada estudiante y su progreso en la carrera</p>
    </div>

    <div class="stats-bar">
      <div class="stat-box">
        <div class="stat-number"><?= count($estudiantes) ?></div>
        <div class="stat-label">Total de Estudiantes</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">
          <?= count(array_filter($estudiantes, fn($e) => $e['activo'] == 1)) ?>
        </div>
        <div class="stat-label">Estudiantes Activos</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">
          <?= count(array_filter($estudiantes, fn($e) => $e['id_carrera'] !== null)) ?>
        </div>
        <div class="stat-label">Con Carrera Asignada</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">
          <?= count(array_filter($estudiantes, fn($e) => $e['anio_actual'] !== null)) ?>
        </div>
        <div class="stat-label">Con Año Manual</div>
      </div>
    </div>

    <?php if (empty($estudiantes)): ?>
      <div class="alert alert-info">
        📌 <strong>No hay estudiantes registrados.</strong>
      </div>
    <?php else: ?>
      <?php foreach ($estudiantes as $est): ?>
        <div class="card-estudiante">
          <div class="estudiante-header">
            <div class="estudiante-info">
              <h4>
                👤 <?= htmlspecialchars($est['apellido'] . ', ' . $est['nombre']) ?>
                <?php if ($est['activo']): ?>
                  <span class="badge-custom badge-activo">Activo</span>
                <?php else: ?>
                  <span class="badge-custom badge-inactivo">Inactivo</span>
                <?php endif; ?>
              </h4>
              <div class="estudiante-detalles">
                <div class="detalle-item">
                  <span>📋</span>
                  <strong>DNI:</strong> <?= htmlspecialchars($est['dni']) ?>
                </div>
                <div class="detalle-item">
                  <span>📧</span>
                  <strong>Email:</strong> <?= htmlspecialchars($est['email']) ?>
                </div>
                <?php if ($est['carrera']): ?>
                <div class="detalle-item">
                  <span>🎓</span>
                  <strong>Carrera:</strong> <?= htmlspecialchars($est['carrera']) ?>
                </div>
                <?php endif; ?>
              </div>
              <?php if ($est['id_carrera']): ?>
                <div class="stats-estudiante">
                  <div class="stat-mini">
                    📚 Cursando: <strong><?= $est['stats']['total_cursando'] ?></strong>
                  </div>
                  <div class="stat-mini">
                    📋 Regular: <strong><?= $est['stats']['total_regular'] ?></strong>
                  </div>
                  <div class="stat-mini">
                    ✅ Aprobadas: <strong><?= $est['stats']['total_aprobada'] ?></strong>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($est['id_carrera']): ?>
            <div class="anio-section">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 style="color: var(--azul-marino-500); margin: 0; font-weight: 600;">
                  📅 Información Académica
                </h5>
                <div>
                  <button class="btn-editar me-2" onclick="abrirModalEditar(<?= $est['id'] ?>, '<?= htmlspecialchars($est['apellido'] . ', ' . $est['nombre']) ?>', <?= $est['anio_ingreso'] ?? 'null' ?>, <?= $est['anio_actual'] ?? 'null' ?>, <?= $est['duracion_carrera'] ?? 4 ?>)">
                    ✏️ Editar Año
                  </button>
                  <?php if ($est['activo']): ?>
                  <a href="?action=ver_analitico&id_est=<?= $est['id'] ?>" class="btn btn-analitico">
                    📊 Analítico
                  </a>
                  <?php endif; ?>
                </div>
              </div>

              <div class="anio-info">
                <div class="anio-box">
                  <div class="anio-label">📆 Año de Ingreso</div>
                  <div class="anio-value">
                    <?= $est['anio_ingreso'] ?? 'No definido' ?>
                  </div>
                </div>
                <div class="anio-box">
                  <div class="anio-label">🎯 Año Actual</div>
                  <div class="anio-value">
                    <?= $est['anio_calculado'] ?>° año
                  </div>
                  <?php if ($est['anio_actual'] !== null): ?>
                    <span class="badge-custom badge-manual mt-2">⚙️ Manual</span>
                  <?php else: ?>
                    <span class="badge-custom badge-auto mt-2">🤖 Automático</span>
                  <?php endif; ?>
                </div>
                <div class="anio-box">
                  <div class="anio-label">⏱️ Años Transcurridos</div>
                  <div class="anio-value">
                    <?php 
                      if ($est['anio_ingreso']) {
                        echo (date('Y') - $est['anio_ingreso']);
                      } else {
                        echo '0';
                      }
                    ?> años
                  </div>
                </div>
                <div class="anio-box">
                  <div class="anio-label">📊 Progreso Carrera</div>
                  <div class="anio-value">
                    <?php 
                      $duracion = $est['duracion_carrera'] ?? 4;
                      $progreso = round(($est['anio_calculado'] / $duracion) * 100);
                      echo min($progreso, 100);
                    ?>%
                  </div>
                </div>
              </div>

              <?php if ($est['anio_actual'] !== null): ?>
                <div class="alert alert-info mt-3 mb-0">
                  ℹ️ <strong>Modo Manual:</strong> El año académico está configurado manualmente. 
                  Para volver al cálculo automático, edita y selecciona "Automático".
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="alert alert-info mb-0">
              ⚠️ Este estudiante aún no está inscrito en ninguna carrera.
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Modal para Editar Año Académico -->
  <div class="modal fade" id="modalEditarAnio" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">✏️ Editar Año Académico</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <h6 class="mb-3" style="color: var(--azul-marino-500); font-weight: 600;">
            Estudiante: <span id="modal_nombre_estudiante"></span>
          </h6>

          <!-- Formulario para Año de Ingreso -->
          <form method="POST" class="mb-4 p-3" style="background: #f8f9fa; border-radius: 12px;">
            <input type="hidden" name="accion" value="actualizar_ingreso">
            <input type="hidden" name="id_estudiante" id="modal_id_estudiante_ingreso">
            <h6 style="color: var(--azul-marino-500); margin-bottom: 1rem;">
              📆 Actualizar Año de Ingreso
            </h6>
            <div class="row">
              <div class="col-md-8">
                <label class="form-label">Año de Ingreso: *</label>
                <input type="number" name="anio_ingreso" id="modal_anio_ingreso" 
                       class="form-control" min="2000" max="<?= date('Y') + 1 ?>" required>
                <small class="text-muted">
                  Año en que el estudiante se inscribió en la carrera
                </small>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-editar w-100">
                  💾 Actualizar Ingreso
                </button>
              </div>
            </div>
          </form>

          <!-- Formulario para Año Actual -->
          <form method="POST" class="p-3" style="background: #f8f9fa; border-radius: 12px;">
            <input type="hidden" name="accion" value="actualizar_anio">
            <input type="hidden" name="id_estudiante" id="modal_id_estudiante_anio">
            <h6 style="color: var(--azul-marino-500); margin-bottom: 1rem;">
              🎯 Configurar Año Académico Actual
            </h6>
            <div class="mb-3">
              <label class="form-label">Año Académico: *</label>
              <select name="anio_actual" id="modal_anio_actual" class="form-select" required>
                <option value="auto">🤖 Automático (Calculado por sistema)</option>
                <option value="1">1° Año</option>
                <option value="2">2° Año</option>
                <option value="3">3° Año</option>
                <option value="4">4° Año</option>
                <option value="5">5° Año</option>
                <option value="6">6° Año</option>
              </select>
              <small class="text-muted">
                Selecciona "Automático" para que el sistema calcule el año según la fecha de ingreso
              </small>
            </div>
            <div class="mb-3">
              <label class="form-label">Motivo del Cambio (opcional):</label>
              <textarea name="motivo" class="form-control" rows="2" 
                        placeholder="Ej: Estudiante repite año por motivos personales..."></textarea>
            </div>
            <div class="alert alert-info">
              💡 <strong>Nota:</strong>
              <ul class="mb-0 mt-2">
                <li><strong>Automático:</strong> El estudiante avanza de año automáticamente cada año calendario</li>
                <li><strong>Manual:</strong> Útil para casos especiales (repitencia, licencias, etc.)</li>
              </ul>
            </div>
            <button type="submit" class="btn btn-editar w-100">
              💾 Guardar Cambios
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para Analítico Académico -->
  <div class="modal fade" id="modalAnalitico" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">📋 Analítico Académico</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="analiticoContent">
          <?php if ($analitico_data): ?>
            <div class="analitico-header">
              <h2>Analítico Académico</h2>
              <div class="analitico-info">
                <div class="info-item">
                  <span class="info-label">Estudiante</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['nombre_completo']) ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">DNI</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['dni']) ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Carrera</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['carrera']) ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Año de Ingreso</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['anio_ingreso']) ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Año Actual</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['anio_actual']) ?> (<?= htmlspecialchars($analitico_data['estudiante']['modo_anio']) ?>)</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Progreso Carrera</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['progreso_carrera']) ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Email</span>
                  <span class="info-value"><?= htmlspecialchars($analitico_data['estudiante']['email']) ?></span>
                </div>
              </div>

              <div class="estadisticas-generales">
                <div class="stat-general">
                  <div class="stat-general-value"><?= $analitico_data['estadisticas']['total_materias'] ?></div>
                  <div class="stat-general-label">Total Materias</div>
                </div>
                <div class="stat-general">
                  <div class="stat-general-value"><?= $analitico_data['estadisticas']['aprobadas'] ?></div>
                  <div class="stat-general-label">Aprobadas</div>
                </div>
                <div class="stat-general">
                  <div class="stat-general-value"><?= $analitico_data['estadisticas']['porc_aprobadas'] ?>%</div>
                  <div class="stat-general-label">% Aprobadas</div>
                </div>
                <div class="stat-general">
                  <div class="stat-general-value"><?= $analitico_data['estadisticas']['porc_asistencia_prom'] ?>%</div>
                  <div class="stat-general-label">Prom. Asistencia</div>
                </div>
                <div class="stat-general">
                  <div class="stat-general-value"><?= $analitico_data['estadisticas']['regular'] ?></div>
                  <div class="stat-general-label">Regular</div>
                </div>
                <div class="stat-general">
                  <div class="stat-general-value"><?= $analitico_data['estadisticas']['cursando'] ?></div>
                  <div class="stat-general-label">Cursando</div>
                </div>
              </div>
            </div>

            <?php
            // Agrupar por carrera
            $analitico_por_carrera = [];
            foreach ($analitico_data['data'] as $fila) {
                $carrera = $fila['carrera'];
                $analitico_por_carrera[$carrera][] = $fila;
            }
            ?>

            <?php foreach ($analitico_por_carrera as $carrera => $materias): ?>
              <div class="carrera-section">
                <h6 class="carrera-header">📚 <?= htmlspecialchars($carrera) ?></h6>
                <div class="table-responsive">
                  <table class="analitico-table">
                    <thead>
                      <tr>
                        <th>Materia</th>
                        <th class="text-center">Año</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Nota Final</th>
                        <th class="text-center">Asistencia (%)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($materias as $m): ?>
                        <tr>
                          <td><?= htmlspecialchars($m['materia']) ?></td>
                          <td class="text-center"><?= htmlspecialchars($m['año']) ?></td>
                          <td class="text-center">
                            <?php if ($m['aprobada'] == 1): ?>
                              <span class="badge estado-aprobada">✅ Aprobada</span>
                            <?php elseif ($m['regular'] == 1): ?>
                              <span class="badge estado-regular">📋 Regular</span>
                            <?php elseif ($m['cursada'] == 1): ?>
                              <span class="badge estado-cursando">📚 Cursando</span>
                            <?php else: ?>
                              <span class="badge estado-sin-estado">⏹ Sin estado</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center">
                            <?= $m['calificacion_final'] ? htmlspecialchars($m['calificacion_final']) : '—' ?>
                          </td>
                          <td class="text-center">
                            <?= ($m['asistencia'] > 0) ? htmlspecialchars($m['asistencia']) . '%' : '—' ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($analitico_data['data'])): ?>
              <div class="alert alert-info">
                📌 Este estudiante no tiene materias registradas en su analítico.
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="alert alert-warning">
              ⚠️ No se pudo cargar el analítico. Verifica que el estudiante esté activo.
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨️ Imprimir</button>
        </div>
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

      // Abrir modal de analítico si se solicitó
      if (window.location.search.includes('action=ver_analitico')) {
        const modal = new bootstrap.Modal(document.getElementById('modalAnalitico'));
        modal.show();
      }
    });

    // Abrir modal de edición
    function abrirModalEditar(idEstudiante, nombreEstudiante, anioIngreso, anioActual, duracionCarrera) {
      // Establecer nombre del estudiante
      document.getElementById('modal_nombre_estudiante').textContent = nombreEstudiante;
      // Establecer IDs en ambos formularios
      document.getElementById('modal_id_estudiante_ingreso').value = idEstudiante;
      document.getElementById('modal_id_estudiante_anio').value = idEstudiante;
      // Establecer año de ingreso
      if (anioIngreso !== null && anioIngreso !== 'null') {
        document.getElementById('modal_anio_ingreso').value = anioIngreso;
      } else {
        document.getElementById('modal_anio_ingreso').value = new Date().getFullYear();
      }
      // Establecer año actual
      const selectAnio = document.getElementById('modal_anio_actual');
      if (anioActual !== null && anioActual !== 'null') {
        selectAnio.value = anioActual;
      } else {
        selectAnio.value = 'auto';
      }
      // Limpiar opciones y agregar solo las necesarias según duración de carrera
      selectAnio.innerHTML = '<option value="auto">🤖 Automático (Calculado por sistema)</option>';
      for (let i = 1; i <= duracionCarrera; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + '° Año';
        selectAnio.appendChild(option);
      }
      // Re-establecer el valor seleccionado
      if (anioActual !== null && anioActual !== 'null') {
        selectAnio.value = anioActual;
      }
      // Abrir modal
      const modal = new bootstrap.Modal(document.getElementById('modalEditarAnio'));
      modal.show();
    }
  </script>
</body>
</html>