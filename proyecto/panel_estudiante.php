<?php
// ============================================================================
// PANEL DE ESTUDIANTE - SISCOL
// ============================================================================
// Archivo único optimizado que contiene toda la lógica, vistas y estilos
// para el panel de control del estudiante
// ============================================================================

session_start();

// ----------------------------------------------------------------------------
// SECCIÓN 1: CONFIGURACIÓN Y SEGURIDAD
// ----------------------------------------------------------------------------

require_once "conexion.php";

// Verificar autenticación y rol
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] !== "estudiante") {
    header("Location: index.php");
    exit();
}

// Variables de sesión
$usuario = $_SESSION["usuario"];
$id_estudiante = (int)$_SESSION["id_usuario"];
$tipo_usuario = $_SESSION["rol"];

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Función para verificar token CSRF
function verificarCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token_recibido = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $token_recibido)) {
            die('Error de seguridad: Token CSRF inválido');
        }
    }
}

// Conectar a la base de datos
$conn = conectar();
if (!$conn) {
    die('<div class="alert alert-danger">Error de conexión a la base de datos.</div>');
}

// Variables para mensajes
$mensaje_exito = $error = null;

// ----------------------------------------------------------------------------
// SECCIÓN 2: FUNCIÓN DE AUDITORÍA
// ----------------------------------------------------------------------------

function registrarAuditoria($conn, $id_usuario, $tipo_usuario, $usuario_nombre, $accion, $resultado, $motivo = null, $objeto_afectado = null, $id_objeto = null, $campo_modificado = null, $valor_anterior = null, $valor_nuevo = null) {
    $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id();

    $sql = "INSERT INTO auditoria (
        id_usuario, tipo_usuario, usuario_nombre, accion, resultado, motivo_fallo,
        objeto_afectado, id_objeto, campo_modificado, valor_anterior, valor_nuevo,
        ip_origen, user_agent, session_id, fecha_hora
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "issssssssssss",
            $id_usuario, $tipo_usuario, $usuario_nombre, $accion, $resultado,
            $motivo, $objeto_afectado, $id_objeto, $campo_modificado,
            $valor_anterior, $valor_nuevo, $ip_origen, $user_agent, $session_id
        );
        $stmt->execute();
        $stmt->close();
    }
}

// ----------------------------------------------------------------------------
// SECCIÓN 3: PROCESAMIENTO DE FORMULARIOS
// ----------------------------------------------------------------------------

// 3.1 INSCRIPCIÓN EN CARRERA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'inscribir_carrera') {
    verificarCSRF();
    $id_carrera = (int)($_POST['id_carrera'] ?? 0);

    // Verificar que la carrera existe y está activa
    $stmt = $conn->prepare("SELECT nombre FROM carreras WHERE id_carrera = ? AND estado = 'activa'");
    $stmt->bind_param("i", $id_carrera);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        $error = "❌ La carrera seleccionada no es válida.";
        registrarAuditoria($conn, $id_estudiante, $tipo_usuario, $usuario, 'INSCRIBIR_CARRERA', 'FALLIDO', $error, 'carreras', $id_carrera);
    } else {
        $nombre_carrera = $result->fetch_assoc()['nombre'];
        $anio_actual = date('Y');
        
        $conn->begin_transaction();
        try {
            // Actualizar carrera y año de ingreso
            $upd = $conn->prepare("UPDATE estudiante SET id_carrera = ?, anio_ingreso = ? WHERE id = ?");
            $upd->bind_param("iii", $id_carrera, $anio_actual, $id_estudiante);
            $upd->execute();
            $upd->close();

            // Insertar materias en analítico
            $ins = $conn->prepare("
                INSERT IGNORE INTO analitico (id_estudiante, id_materia, cursada, regular, aprobada, activo)
                SELECT ?, m.id, 0, 0, 0, 1
                FROM materias m
                WHERE m.id_carrera = ? AND m.activo = 1
            ");
            $ins->bind_param("ii", $id_estudiante, $id_carrera);
            $ins->execute();
            $ins->close();

            $conn->commit();
            $mensaje_exito = "✅ ¡Inscripción exitosa en $nombre_carrera! Ahora puedes seleccionar materias de primer año.";
            registrarAuditoria($conn, $id_estudiante, $tipo_usuario, $usuario, 'INSCRIBIR_CARRERA', 'EXITO', null, 'estudiante', $id_estudiante, 'id_carrera', null, $id_carrera);
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ Error al procesar la inscripción.";
            registrarAuditoria($conn, $id_estudiante, $tipo_usuario, $usuario, 'INSCRIBIR_CARRERA', 'FALLIDO', $error, 'estudiante', $id_estudiante);
        }
    }
    $stmt->close();
}

// 3.2 SELECCIÓN DE MATERIAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'seleccionar_materias') {
    verificarCSRF();
    $materias_seleccionadas = $_POST['materias'] ?? [];
    $ids = array_map('intval', $materias_seleccionadas);

    if (empty($ids)) {
        $error = "❌ No se seleccionaron materias.";
    } else {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // Verificar que las materias pertenecen al estudiante
        $verify_sql = "SELECT COUNT(*) as total FROM analitico a
                       WHERE a.id_estudiante = ? AND a.id_materia IN ($placeholders) AND a.activo = 1";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_params = array_merge([$id_estudiante], $ids);
        $verify_types = str_repeat('i', count($verify_params));
        $verify_stmt->bind_param($verify_types, ...$verify_params);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();

        if ($verify_result['total'] != count($ids)) {
            $error = "❌ Algunas materias seleccionadas no son válidas.";
        } else {
            $update_sql = "UPDATE analitico SET cursada = 1 WHERE id_estudiante = ? AND id_materia IN ($placeholders) AND activo = 1";
            $upd = $conn->prepare($update_sql);
            $upd->bind_param($verify_types, ...$verify_params);
            
            if ($upd->execute()) {
                $mensaje_exito = "✅ Materias seleccionadas correctamente.";
                registrarAuditoria($conn, $id_estudiante, $tipo_usuario, $usuario, 'SELECCIONAR_MATERIAS', 'EXITO', null, 'analitico', null, 'cursada', '0', '1');
            } else {
                $error = "❌ Error al guardar la selección.";
            }
            $upd->close();
        }
    }
}

// 3.3 INSCRIPCIÓN A FINAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'inscribir_final') {
    verificarCSRF();
    $id_materia = (int)($_POST['id_materia_final'] ?? 0);

    $stmt = $conn->prepare("
        SELECT regular, aprobada, inscripto_para_final
        FROM analitico
        WHERE id_estudiante = ? AND id_materia = ? AND activo = 1
    ");
    $stmt->bind_param("ii", $id_estudiante, $id_materia);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['regular'] == 1 && $row['aprobada'] == 0 && $row['inscripto_para_final'] == 0) {
            $upd = $conn->prepare("UPDATE analitico SET inscripto_para_final = 1 WHERE id_estudiante = ? AND id_materia = ?");
            $upd->bind_param("ii", $id_estudiante, $id_materia);
            
            if ($upd->execute()) {
                $mensaje_exito = "✅ ¡Te has inscrito correctamente para el final!";
                registrarAuditoria($conn, $id_estudiante, $tipo_usuario, $usuario, 'INSCRIBIR_FINAL', 'EXITO', null, 'analitico', null, 'inscripto_para_final', '0', '1');
            } else {
                $error = "❌ Error al inscribirse para el final.";
            }
            $upd->close();
        } else {
            $error = $row['inscripto_para_final'] == 1 ? "❌ Ya estás inscrito para el final." :
                     ($row['aprobada'] == 1 ? "❌ Esta materia ya está aprobada." :
                     "❌ Debes estar regular para inscribirte al final.");
        }
    } else {
        $error = "❌ Materia no válida.";
    }
    $stmt->close();
}

// ----------------------------------------------------------------------------
// SECCIÓN 4: OBTENER DATOS DEL ESTUDIANTE
// ----------------------------------------------------------------------------

// Obtener datos del estudiante y carrera en una sola consulta optimizada
$estudiante_stmt = $conn->prepare("
    SELECT e.id_carrera, e.anio_ingreso, e.anio_actual, c.nombre as nombre_carrera, c.duracion
    FROM estudiante e
    LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
    WHERE e.id = ?
");
$estudiante_stmt->bind_param("i", $id_estudiante);
$estudiante_stmt->execute();
$estudiante = $estudiante_stmt->get_result()->fetch_assoc();
$estudiante_stmt->close();

$nombre_carrera = $estudiante['nombre_carrera'];

// Calcular año académico del estudiante
$anio_estudiante = 1;
$mensaje_anio = "";

if ($estudiante['id_carrera'] !== null && $estudiante['anio_ingreso'] !== null) {
    if ($estudiante['anio_actual'] !== null) {
        $anio_estudiante = (int)$estudiante['anio_actual'];
        $mensaje_anio = "📌 Estás cursando <strong>{$anio_estudiante}° año</strong> (configuración manual).";
    } else {
        $anio_estudiante = min((int)date('Y') - (int)$estudiante['anio_ingreso'] + 1, (int)($estudiante['duracion'] ?? 4));
        $mensaje_anio = "📌 Estás cursando <strong>{$anio_estudiante}° año</strong> (ingresaste en {$estudiante['anio_ingreso']}).";
    }
}

// Obtener carreras disponibles si no está inscrito
$carreras_result = null;
if ($estudiante['id_carrera'] === null) {
    $carreras_stmt = $conn->prepare("SELECT id_carrera, nombre FROM carreras WHERE estado = 'activa' ORDER BY nombre");
    $carreras_stmt->execute();
    $carreras_result = $carreras_stmt->get_result();
    $carreras_stmt->close();
}

// ----------------------------------------------------------------------------
// SECCIÓN 5: OBTENER MATERIAS Y CORRELATIVIDADES
// ----------------------------------------------------------------------------

$materias_habilitadas = [];
$materias_cursando = [];
$materias_otros_anios = [];

if ($estudiante['id_carrera'] !== null) {
    // Obtener todas las materias con su estado
    $materias_stmt = $conn->prepare("
        SELECT m.id, m.nombre, m.año, a.cursada, a.regular, a.aprobada, a.inscripto_para_final
        FROM analitico a
        INNER JOIN materias m ON a.id_materia = m.id
        WHERE a.id_estudiante = ? AND a.activo = 1
        ORDER BY m.año, m.nombre
    ");
    $materias_stmt->bind_param("i", $id_estudiante);
    $materias_stmt->execute();
    $result = $materias_stmt->get_result();
    
    $todas_materias = [];
    while ($row = $result->fetch_assoc()) {
        $todas_materias[$row['id']] = $row;
        if ($row['cursada'] == 1) {
            $materias_cursando[] = $row;
        }
    }
    $materias_stmt->close();

    // Obtener correlatividades y estado en una sola consulta
    if (!empty($todas_materias)) {
        $ids_materias = array_keys($todas_materias);
        $placeholders = str_repeat('?,', count($ids_materias) - 1) . '?';
        
        $corr_stmt = $conn->prepare("
            SELECT id_materia, id_materia_correlativa, tipo_condicion
            FROM correlatividades
            WHERE id_materia IN ($placeholders) AND activo = 1
        ");
        $types = str_repeat('i', count($ids_materias));
        $corr_stmt->bind_param($types, ...$ids_materias);
        $corr_stmt->execute();
        $corr_result = $corr_stmt->get_result();
        
        $correlativas = [];
        while ($row = $corr_result->fetch_assoc()) {
            $correlativas[$row['id_materia']][] = $row;
        }
        $corr_stmt->close();

        // Filtrar materias habilitadas
        foreach ($todas_materias as $id_materia => $materia) {
            if ($materia['cursada'] == 1) continue;

            $anio_materia = (int)$materia['año'];
            
            if ($anio_materia != $anio_estudiante) {
                if ($anio_materia > $anio_estudiante) {
                    $materias_otros_anios[] = $materia;
                }
                continue;
            }

            // Verificar correlatividades
            $habilitada = true;
            if (isset($correlativas[$id_materia])) {
                foreach ($correlativas[$id_materia] as $corr) {
                    $id_corr = $corr['id_materia_correlativa'];
                    $condicion = $corr['tipo_condicion'];
                    
                    if (!isset($todas_materias[$id_corr])) {
                        $habilitada = false;
                        break;
                    }
                    
                    $estado_corr = $todas_materias[$id_corr];
                    if ($condicion === 'aprobada' && $estado_corr['aprobada'] != 1) {
                        $habilitada = false;
                        break;
                    }
                    if ($condicion === 'regular' && $estado_corr['regular'] == 0 && $estado_corr['aprobada'] == 0) {
                        $habilitada = false;
                        break;
                    }
                }
            }

            if ($habilitada) {
                $materias_habilitadas[] = $materia;
            }
        }
    }
}

// ----------------------------------------------------------------------------
// SECCIÓN 6: OBTENER CALIFICACIONES Y ASISTENCIAS
// ----------------------------------------------------------------------------

// Calificaciones
$calif_stmt = $conn->prepare("
    SELECT m.nombre AS materia, a.calificacion_final AS nota
    FROM analitico a
    INNER JOIN materias m ON a.id_materia = m.id
    WHERE a.id_estudiante = ? AND a.calificacion_final IS NOT NULL
    ORDER BY m.año, m.nombre
");
$calif_stmt->bind_param("i", $id_estudiante);
$calif_stmt->execute();
$calif_result = $calif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$calif_stmt->close();

// Asistencias
$asist_stmt = $conn->prepare("
    SELECT a.fecha, m.nombre AS materia, a.presente
    FROM asistencias a
    INNER JOIN materias m ON a.id_materia = m.id
    WHERE a.id_usuario = ?
    ORDER BY a.fecha DESC
    LIMIT 50
");
$asist_stmt->bind_param("i", $id_estudiante);
$asist_stmt->execute();
$asist_result = $asist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$asist_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SISCOL - Panel Estudiante</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* =========================================================================
   VARIABLES Y RESET
   ========================================================================= */
:root {
  --azul-marino-500: #1E3A5F;
  --azul-marino-600: #1a3354;
  --azul-marino-700: #162b45;
  --verde-olivo-500: #556B2F;
  --verde-olivo-600: #4a5e29;
  --verde-olivo-700: #3f5023;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'Inter', 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, #0d1a28, #162b45, #1a3354);
  color: white;
  min-height: 100vh;
}

/* =========================================================================
   TOPBAR Y NAVEGACIÓN
   ========================================================================= */
.topbar {
  background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
  padding: 1rem 1.5rem;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
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

.logout-btn {
  color: white;
  text-decoration: none;
  padding: 0.5rem 1.25rem;
  background: rgba(255,255,255,0.1);
  border-radius: 8px;
  transition: all 0.3s ease;
  font-weight: 500;
}

.logout-btn:hover {
  background: rgba(255,255,255,0.2);
  color: white;
  transform: translateY(-2px);
}

/* =========================================================================
   SIDEBAR
   ========================================================================= */
.sidebar {
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(10px);
  border-right: 1px solid rgba(255,255,255,0.1);
  padding: 2rem 1.5rem;
  min-height: calc(100vh - 70px);
}

.sidebar-title {
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid var(--verde-olivo-500);
}

.menu-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: rgba(255,255,255,0.8);
  text-decoration: none;
  padding: 0.875rem 1rem;
  margin-bottom: 0.5rem;
  border-radius: 10px;
  transition: all 0.3s ease;
  font-weight: 500;
  border-left: 3px solid transparent;
}

.menu-link:hover {
  background: rgba(255,255,255,0.1);
  color: white;
  transform: translateX(5px);
  border-left-color: var(--verde-olivo-500);
}

.menu-link.active {
  background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
  color: white;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  border-left-color: var(--verde-olivo-700);
}

/* =========================================================================
   CARDS Y CONTENEDORES
   ========================================================================= */
.card-clean {
  background: rgba(255,255,255,0.98);
  color: #1a1a1a;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.2);
  overflow: hidden;
}

.card-header-custom {
  background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
  color: white;
  padding: 1.25rem 1.5rem;
  border-bottom: 3px solid var(--azul-marino-700);
  font-weight: 600;
  font-size: 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.card-body-custom { padding: 2rem; }

.welcome-card {
  background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
  color: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.welcome-card h3 {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

.info-badge {
  display: inline-block;
  background: rgba(255,255,255,0.2);
  padding: 0.5rem 1.25rem;
  border-radius: 8px;
  margin-top: 1rem;
  margin-right: 0.5rem;
  font-weight: 500;
}

/* =========================================================================
   ESTADÍSTICAS
   ========================================================================= */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  border-left: 4px solid var(--verde-olivo-500);
}

.stat-number {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--azul-marino-500);
  margin: 0.5rem 0;
}

.stat-label {
  color: #666;
  font-weight: 500;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* =========================================================================
   BOTONES
   ========================================================================= */
.btn-accion {
  background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
  color: white;
  border: none;
  padding: 0.75rem 1.75rem;
  border-radius: 10px;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-accion:hover {
  background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  color: white;
}

.btn-final {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.btn-final:hover {
  background: linear-gradient(135deg, #0056b3, #004085);
  color: white;
  transform: translateY(-1px);
}

.btn-final:disabled {
  background: #6c757d;
  cursor: not-allowed;
  transform: none;
}

/* =========================================================================
   TABLAS
   ========================================================================= */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  margin-top: 1.5rem;
}

.table { margin-bottom: 0; }

.table thead th {
  background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));
  color: white;
  font-weight: 600;
  padding: 1rem;
  border: none;
  text-transform: uppercase;
  font-size: 0.85rem;
  letter-spacing: 0.5px;
}

.table tbody tr {
  transition: all 0.2s ease;
  border-bottom: 1px solid #f0f0f0;
}

.table tbody tr:hover {
  background: rgba(85, 107, 47, 0.05);
  transform: scale(1.01);
}

.table tbody td {
  padding: 1rem;
  vertical-align: middle;
  color: #333;
}

/* =========================================================================
   FORMULARIOS Y CONTROLES
   ========================================================================= */
.form-control, .form-select {
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  transition: all 0.3s ease;
  background: #fafafa;
}

.form-control:focus, .form-select:focus {
  border-color: var(--verde-olivo-500);
  box-shadow: 0 0 0 0.2rem rgba(85, 107, 47, 0.15);
  background: white;
}

.form-label {
  font-weight: 600;
  color: #333;
  margin-bottom: 0.5rem;
}

.checkbox-custom {
  width: 20px;
  height: 20px;
  cursor: pointer;
  accent-color: var(--verde-olivo-500);
}

/* =========================================================================
   ALERTAS Y BADGES
   ========================================================================= */
.alert {
  border-radius: 12px;
  border: none;
  padding: 1rem 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  font-weight: 500;
}

.alert-success { background: linear-gradient(135deg, #d4edda, #c3e6cb); color: #155724; }
.alert-danger { background: linear-gradient(135deg, #f8d7da, #f5c6cb); color: #721c24; }
.alert-info { background: linear-gradient(135deg, #d1ecf1, #bee5eb); color: #0c5460; }
.alert-warning { background: linear-gradient(135deg, #fff3cd, #ffeeba); color: #856404; }

.badge-estado {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.85rem;
}

.badge-aprobada { background: #d4edda; color: #155724; }
.badge-cursando { background: #d1ecf1; color: #0c5460; }
.badge-regular { background: #fff3cd; color: #856404; }
.badge-inscripto { background: #b3d9ff; color: #004085; }

/* =========================================================================
   ANIMACIONES Y SECCIONES
   ========================================================================= */
.seccion {
  display: none;
  animation: fadeIn 0.4s ease;
}

.seccion.active { display: block; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* =========================================================================
   RESPONSIVE
   ========================================================================= */
@media (max-width: 768px) {
  .sidebar { min-height: auto; padding: 1rem; }
  .card-body-custom { padding: 1.5rem; }
  .welcome-card h3 { font-size: 1.5rem; }
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body>
<!-- ========================================================================
     BARRA SUPERIOR
     ======================================================================== -->
<nav class="navbar topbar">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">🎓 SISCOL • Estudiante</a>
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($usuario) ?></span>
      <a href="logout.php" class="logout-btn">Cerrar sesión</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row g-0">
    
    <!-- ====================================================================
         SIDEBAR MENÚ
         ==================================================================== -->
    <aside class="col-12 col-md-3 col-lg-2 sidebar">
      <div class="sidebar-title">📋 Menú Principal</div>
      <a href="#" class="menu-link active" data-target="inicio">
        <span>🏠</span> Inicio
      </a>
      <a href="#" class="menu-link" data-target="materias">
        <span>📚</span> Mis Materias
      </a>
      <a href="#" class="menu-link" data-target="calificaciones">
        <span>📝</span> Calificaciones
      </a>
      <a href="#" class="menu-link" data-target="asistencias">
        <span>📅</span> Asistencias
      </a>
    </aside>

    <!-- ====================================================================
         CONTENIDO PRINCIPAL
         ==================================================================== -->
    <main class="col-12 col-md-9 col-lg-10 p-4">
      
      <!-- Mensajes de éxito/error -->
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

      <!-- ==================================================================
           SECCIÓN: INICIO
           ================================================================== -->
      <section id="inicio" class="seccion active">
        <div class="welcome-card">
          <h3>¡Bienvenido/a, <?= htmlspecialchars($usuario) ?>! 🎓</h3>
          <?php if ($estudiante['id_carrera'] !== null): ?>
          <div class="info-badge">
            <strong>📖 Tu carrera:</strong> <?= htmlspecialchars($nombre_carrera) ?>
          </div>
          <div class="info-badge">
            <strong>🎯 Año actual:</strong> <?= $anio_estudiante ?>° año
          </div>
          <?php else: ?>
          <div class="alert alert-warning mt-3">
            ⚠️ <strong>Atención:</strong> Aún no te has inscrito en ninguna carrera. Dirígete a "Mis Materias" para inscribirte.
          </div>
          <?php endif; ?>
        </div>

        <?php if ($estudiante['id_carrera'] !== null): ?>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">📚 Materias Cursando</div>
            <div class="stat-number"><?= count($materias_cursando) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">✅ Disponibles (<?= $anio_estudiante ?>° año)</div>
            <div class="stat-number"><?= count($materias_habilitadas) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">📝 Calificaciones</div>
            <div class="stat-number"><?= count($calif_result) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">📅 Registros Asistencia</div>
            <div class="stat-number"><?= count($asist_result) ?></div>
          </div>
        </div>
        <?php endif; ?>
      </section>

      <!-- ==================================================================
           SECCIÓN: MIS MATERIAS
           ================================================================== -->
      <section id="materias" class="seccion">
        <div class="card card-clean">
          <div class="card-header-custom">📚 Mis Materias</div>
          <div class="card-body-custom">
            
            <?php if ($estudiante['id_carrera'] === null): ?>
            <!-- Formulario de inscripción a carrera -->
            <div class="alert alert-info mb-4">
              📌 <strong>Primer paso:</strong> Debes inscribirte en una carrera para poder ver y seleccionar materias.
            </div>

            <?php if ($carreras_result && $carreras_result->num_rows > 0): ?>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" name="accion" value="inscribir_carrera">
              <div class="mb-4">
                <label class="form-label">Selecciona tu carrera:</label>
                <select name="id_carrera" class="form-select" required>
                  <option value="">-- Elige una opción --</option>
                  <?php while ($c = $carreras_result->fetch_assoc()): ?>
                  <option value="<?= (int)$c['id_carrera'] ?>">
                    <?= htmlspecialchars($c['nombre']) ?>
                  </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-accion">
                📝 Inscribirme en esta carrera
              </button>
            </form>
            <?php endif; ?>

            <?php else: ?>
            <!-- Estudiante ya inscrito en carrera -->
            <div class="alert alert-info mb-4">
              <?= $mensaje_anio ?>
              <br><small>Solo puedes inscribirte en materias correspondientes a tu año actual.</small>
            </div>

            <!-- Materias que está cursando -->
            <?php if (!empty($materias_cursando)): ?>
            <div class="mb-5">
              <h5 class="mb-3" style="color: var(--azul-marino-500); font-weight: 600;">
                📖 Materias que estás cursando
              </h5>
              <div class="table-container">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Materia</th>
                      <th>Año</th>
                      <th>Estado</th>
                      <th style="width: 150px; text-align: center;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($materias_cursando as $m): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($m['nombre']) ?></strong></td>
                      <td><?= $m['año'] ?>° Año</td>
                      <td>
                        <?php if ($m['aprobada'] == 1): ?>
                        <span class="badge-estado badge-aprobada">✅ Aprobada</span>
                        <?php elseif ($m['regular'] == 1): ?>
                        <span class="badge-estado badge-regular">📋 Regular</span>
                        <?php else: ?>
                        <span class="badge-estado badge-cursando">📚 Cursando</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <?php if ($m['regular'] == 1 && $m['aprobada'] == 0 && $m['inscripto_para_final'] == 0): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Confirmas tu inscripción al final de <?= addslashes(htmlspecialchars($m['nombre'])) ?>?');">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <input type="hidden" name="accion" value="inscribir_final">
                          <input type="hidden" name="id_materia_final" value="<?= (int)$m['id'] ?>">
                          <button type="submit" class="btn btn-final">📝 Inscribirse a Final</button>
                        </form>
                        <?php elseif ($m['inscripto_para_final'] == 1): ?>
                        <span class="badge-estado badge-inscripto">📝 Inscripto</span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>

            <!-- Materias disponibles para inscribirse -->
            <?php if (!empty($materias_habilitadas)): ?>
            <h5 class="mb-3" style="color: var(--azul-marino-500); font-weight: 600;">
              ✨ Materias disponibles de <?= $anio_estudiante ?>° año
            </h5>
            <div class="alert alert-info mb-3">
              💡 <strong>Tip:</strong> Selecciona las materias que deseas cursar y haz clic en "Guardar Selección".
            </div>

            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" name="accion" value="seleccionar_materias">
              <div class="table-container">
                <table class="table">
                  <thead>
                    <tr>
                      <th style="width: 60px;">Seleccionar</th>
                      <th>Materia</th>
                      <th>Año</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($materias_habilitadas as $m): ?>
                    <tr>
                      <td class="text-center">
                        <input type="checkbox" name="materias[]" value="<?= (int)$m['id'] ?>" class="checkbox-custom">
                      </td>
                      <td><strong><?= htmlspecialchars($m['nombre']) ?></strong></td>
                      <td><?= $m['año'] ?>° Año</td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <button type="submit" class="btn btn-accion mt-3">💾 Guardar Selección</button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning">
              ⚠️ <strong>No hay materias disponibles:</strong> No tienes materias habilitadas para cursar en <?= $anio_estudiante ?>° año.
              <ul class="mt-2 mb-0">
                <li>Ya estás cursando todas las materias de este año</li>
                <li>Necesitas aprobar correlatividades previas</li>
                <li>No hay materias configuradas para este año</li>
              </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($materias_otros_anios)): ?>
            <div class="mt-4 alert alert-info">
              <strong>ℹ️ Materias de años superiores:</strong><br>
              Hay <?= count($materias_otros_anios) ?> materia(s) de años superiores disponibles. Podrás inscribirte cuando avances de año.
            </div>
            <?php endif; ?>

            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- ==================================================================
           SECCIÓN: CALIFICACIONES
           ================================================================== -->
      <section id="calificaciones" class="seccion">
        <div class="card card-clean">
          <div class="card-header-custom">📝 Mis Calificaciones</div>
          <div class="card-body-custom">
            <?php if (!empty($calif_result)): ?>
            <div class="alert alert-info mb-4">
              📊 <strong>Resumen:</strong> Tienes <?= count($calif_result) ?> calificación(es) registrada(s).
            </div>
            <div class="table-container">
              <table class="table">
                <thead>
                  <tr>
                    <th>Materia</th>
                    <th style="width: 150px; text-align: center;">Nota Final</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($calif_result as $fila): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($fila['materia']) ?></strong></td>
                    <td style="text-align: center;">
                      <span style="font-size: 1.5rem; font-weight: 700; color: <?= $fila['nota'] >= 7 ? '#28a745' : ($fila['nota'] >= 4 ? '#ffc107' : '#dc3545') ?>">
                        <?= htmlspecialchars($fila['nota']) ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php
            $total_notas = count($calif_result);
            $suma_notas = array_sum(array_column($calif_result, 'nota'));
            $promedio = $total_notas > 0 ? round($suma_notas / $total_notas, 2) : 0;
            ?>
            <div class="mt-4 p-3" style="background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600)); color: white; border-radius: 12px;">
              <div class="row text-center">
                <div class="col-md-6">
                  <div style="font-size: 0.9rem; opacity: 0.9;">Promedio General</div>
                  <div style="font-size: 2.5rem; font-weight: 700;"><?= $promedio ?></div>
                </div>
                <div class="col-md-6">
                  <div style="font-size: 0.9rem; opacity: 0.9;">Total de Calificaciones</div>
                  <div style="font-size: 2.5rem; font-weight: 700;"><?= $total_notas ?></div>
                </div>
              </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
              📌 <strong>Sin calificaciones:</strong> Aún no tienes calificaciones registradas.
            </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- ==================================================================
           SECCIÓN: ASISTENCIAS
           ================================================================== -->
      <section id="asistencias" class="seccion">
        <div class="card card-clean">
          <div class="card-header-custom">📅 Mis Asistencias</div>
          <div class="card-body-custom">
            <?php if (!empty($asist_result)): ?>
            <div class="alert alert-info mb-4">
              📊 <strong>Registros encontrados:</strong> Se muestran los últimos <?= count($asist_result) ?> registros.
            </div>
            <div class="table-container">
              <table class="table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Materia</th>
                    <th style="width: 120px; text-align: center;">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($asist_result as $fila): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($fila['fecha'])) ?></td>
                    <td><strong><?= htmlspecialchars($fila['materia']) ?></strong></td>
                    <td style="text-align: center;">
                      <?php if ($fila['presente']): ?>
                      <span class="badge-estado badge-aprobada">✅ Presente</span>
                      <?php else: ?>
                      <span style="background: #f8d7da; color: #721c24; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">❌ Ausente</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php
            $total_asistencias = count($asist_result);
            $presentes = count(array_filter($asist_result, fn($a) => $a['presente']));
            $porcentaje = $total_asistencias > 0 ? round(($presentes / $total_asistencias) * 100, 1) : 0;
            ?>
            <div class="mt-4 p-3" style="background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600)); color: white; border-radius: 12px;">
              <div class="row text-center">
                <div class="col-md-4">
                  <div style="font-size: 0.9rem; opacity: 0.9;">Asistencia</div>
                  <div style="font-size: 2.5rem; font-weight: 700;"><?= $porcentaje ?>%</div>
                </div>
                <div class="col-md-4">
                  <div style="font-size: 0.9rem; opacity: 0.9;">Presentes</div>
                  <div style="font-size: 2.5rem; font-weight: 700;"><?= $presentes ?></div>
                </div>
                <div class="col-md-4">
                  <div style="font-size: 0.9rem; opacity: 0.9;">Ausencias</div>
                  <div style="font-size: 2.5rem; font-weight: 700;"><?= $total_asistencias - $presentes ?></div>
                </div>
              </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
              📌 <strong>Sin registros:</strong> Aún no tienes asistencias registradas.
            </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- ========================================================================
     SCRIPTS
     ======================================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const sections = document.querySelectorAll('.seccion');
  const links = document.querySelectorAll('.menu-link');

  // Función para cambiar de sección
  function showSection(targetId) {
    sections.forEach(s => s.classList.remove('active'));
    const target = document.getElementById(targetId);
    if (target) target.classList.add('active');

    links.forEach(l => l.classList.remove('active'));
    const activeLink = Array.from(links).find(l => l.dataset.target === targetId);
    if (activeLink) activeLink.classList.add('active');

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // Event listeners para los links del menú
  links.forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      showSection(this.dataset.target);
    });
  });

  // Auto-ocultar alertas después de 5 segundos
  const alerts = document.querySelectorAll('.alert-success, .alert-danger');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });
});
</script>

</body>
</html>