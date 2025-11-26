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
        // Cadena de tipos: i s s s s s s i s s s s s s (14 caracteres)
        // id_usuario (i), tipo_usuario (s), usuario_nombre (s), accion (s), resultado (s), motivo (s), objeto_afectado (s), id_objeto (i),
        // campo_modificado (s), valor_anterior (s), valor_nuevo (s), ip_origen (s), user_agent (s), session_id (s)
        $stmt_auditoria->bind_param(
            "isssssisssssss", // Cambiado de "issssssssssssss" a "isssssisssssss" para reflejar que id_objeto es 'i'
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

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "secretaria") {
    header("Location: index.php");
    exit();
}

$conn = conectar();
$usuario = $_SESSION["usuario"];
$id_usuario = $_SESSION["id_usuario"];
$tipo_usuario = $_SESSION["rol"]; // "secretaria"

// Registrar el acceso al panel de secretaría
// Se agregan los parámetros faltantes: campo_modificado, valor_anterior, valor_nuevo como null
registrarAuditoria($conn, $id_usuario, $tipo_usuario, $usuario, 'ACCESO_PANEL', 'EXITO', null, 'panel_secretaria', null, null, null, null);

// 1. Contador de Estudiantes → tabla 'estudiante'
$total_estudiantes = $conn->query("SELECT COUNT(*) as total FROM estudiante WHERE activo = 1")->fetch_assoc()['total'] ?? 0;

// 2. Contador de Carreras Activas → tabla 'carreras'
$total_carreras_activas = $conn->query("SELECT COUNT(*) as total FROM carreras WHERE estado = 'activa'")->fetch_assoc()['total'] ?? 0;

// 3. Estadísticas generales → ahora usamos la tabla 'analitico' (reemplazo de inscripciones)
$estadisticas_generales = $conn->query("SELECT COUNT(*) as total FROM analitico WHERE activo = 1")->fetch_assoc()['total'] ?? 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISCOL - Panel Secretaría</title>
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
            <a class="navbar-brand" href="#">SISCOL • Panel Secretaría</a>
            <div class="user-info">
                <span class="user-name">👋 <?= htmlspecialchars($usuario) ?></span>
                <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-custom">
        <div class="page-header">
            <h1>📊 Dashboard de Secretaría</h1>
            <p>Resumen general y acceso rápido a la gestión académica</p>
        </div>

        <div class="stats-bar">
            <div class="stat-box">
                <div class="stat-number"><?= $total_estudiantes ?></div>
                <div class="stat-label">Estudiantes Activos</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $total_carreras_activas ?></div>
                <div class="stat-label">Carreras Activas</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $estadisticas_generales ?></div>
                <div class="stat-label">Registros Analítico</div>
            </div>
        </div>

        <div class="menu-section">
            <h2 class="text-center mb-4" style="color: white; font-weight: 600;">Menú de Gestión</h2>
            <div class="menu-grid">
                <div class="menu-card">
                    <h3 class="menu-header">👥 Gestión de Estudiantes</h3>
                    <ul>
                        <li><a href="secretaria/lista_estudiantes.php">📋 Ver Lista de Estudiantes</a></li>
                    </ul>
                </div>

                <div class="menu-card">
                    <h3 class="menu-header">🎓 Carreras</h3>
                    <ul>
                        <li><a href="secretaria/ver_carreras.php">📚 Ver Carreras</a></li>
                        <li><a href="secretaria/cargar_nueva_carrera.php">➕ Cargar Carrera</a></li>
                        <li><a href="secretaria/ver_materias.php">📖 Ver Materias</a></li>
                        <li><a href="secretaria/gestionar_correlatividades.php">🔗 Gestionar Correlatividades</a></li>
                    </ul>
                </div>

                <div class="menu-card">
                    <h3 class="menu-header">👨‍🏫 Docentes</h3>
                    <ul>
                        <li><a href="secretaria/ver_docentes.php">📋 Ver Docentes</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>