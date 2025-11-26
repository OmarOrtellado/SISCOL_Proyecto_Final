<?php
session_start();
require_once "conexion.php";

// Verificar si el usuario está logueado y es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.php"); // ✅ Cambiado de index.html a index.php
    exit();
}

$usuario = $_SESSION["usuario"];

// Conectar para obtener los datos de auditoría
$conn = conectar();
$auditoria_data = [];
if ($conn) {
    // Obtener los últimos 100 registros de auditoría (ajusta el límite según necesites)
    $sql_auditoria = "SELECT 
        id, 
        id_usuario, 
        tipo_usuario, 
        usuario_nombre, 
        accion, 
        resultado, 
        motivo_fallo, 
        objeto_afectado, 
        id_objeto, 
        campo_modificado, 
        valor_anterior, 
        valor_nuevo, 
        ip_origen, 
        user_agent, 
        session_id, 
        fecha_hora 
        FROM auditoria 
        ORDER BY fecha_hora DESC 
        LIMIT 100";
    
    $result_auditoria = $conn->query($sql_auditoria);
    if ($result_auditoria) {
        while ($row = $result_auditoria->fetch_assoc()) {
            $auditoria_data[] = $row;
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISCOL - Panel Super Usuario</title>
    <!-- ✅ Eliminado espacio al final en el enlace -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            /* Paleta de colores expandida */
            --azul-marino-50: #f0f4f8;
            --azul-marino-100: #d9e6f2;
            --azul-marino-500: #1E3A5F;
            --azul-marino-600: #1a3354;
            --azul-marino-700: #162b45;
            --azul-marino-800: #122336;
            --azul-marino-900: #0d1a28;
            
            --verde-olivo-50: #f7f8f4;
            --verde-olivo-100: #ebede3;
            --verde-olivo-500: #556B2F;
            --verde-olivo-600: #4a5e29;
            --verde-olivo-700: #3f5023;
            --verde-olivo-800: #34421d;
            --verde-olivo-900: #293417;
            
            /* Espaciado consistente */
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--azul-marino-900), var(--azul-marino-700));
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }

        /* Elementos decorativos de fondo */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(85, 107, 47, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(30, 58, 95, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(85, 107, 47, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }

        /* Header mejorado */
        header {
            background: linear-gradient(135deg, var(--verde-olivo-600) 0%, var(--verde-olivo-500) 100%);
            padding: var(--space-6) var(--space-8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
            z-index: 100;
            backdrop-filter: blur(10px);
            animation: slideDown 0.8s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        }

        header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, #ffffff, rgba(255,255,255,0.9));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-welcome {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-5);
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .user-info:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.02);
        }

        header a {
            color: white;
            text-decoration: none;
            font-weight: 700;
            padding: var(--space-3) var(--space-5);
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        header a:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Contenido principal */
        main {
            flex: 1;
            padding: var(--space-8);
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: var(--space-12);
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--space-4);
            background: linear-gradient(135deg, #ffffff, rgba(255,255,255,0.8));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Grid de botones mejorado */
        .panel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
            max-width: 1000px;
            width: 100%;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .panel-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: var(--space-8);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: white;
        }

        .panel-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--verde-olivo-500), var(--azul-marino-500));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .panel-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            color: white;
            text-decoration: none;
        }

        .panel-card:hover::before {
            transform: scaleX(1);
        }

        .panel-icon {
            font-size: 3rem;
            margin-bottom: var(--space-4);
            display: block;
            text-align: center;
        }

        .panel-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--space-3);
            text-align: center;
            color: var(--verde-olivo-100);
        }

        .panel-description {
            font-size: 0.9rem;
            opacity: 0.8;
            line-height: 1.5;
            text-align: center;
            margin-bottom: var(--space-4);
        }

        .panel-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            width: 100%;
            padding: var(--space-3) var(--space-4);
            background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(85, 107, 47, 0.3);
            position: relative;
            overflow: hidden;
        }

        .panel-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .panel-card:hover .panel-action {
            background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(85, 107, 47, 0.4);
        }

        .panel-card:hover .panel-action::before {
            left: 100%;
        }

        /* Estilos para el botón de auditoría */
        .btn-auditar {
            background: linear-gradient(135deg, #6f42c1, #5a32a3); /* Púrpura para diferenciarlo */
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: var(--space-6);
        }

        .btn-auditar:hover {
            background: linear-gradient(135deg, #5a32a3, #4a288a);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(111, 66, 193, 0.4);
        }

        /* Estilos para la tabla de auditoría */
        .auditoria-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .auditoria-table th,
        .auditoria-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 0.85rem;
        }

        .auditoria-table th {
            background: var(--azul-marino-800);
            color: var(--verde-olivo-300);
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .auditoria-table tr:hover {
            background: rgba(255,255,255,0.05);
        }

        .resultado-exito {
            color: #4caf50;
            font-weight: bold;
        }

        .resultado-fallido {
            color: #f44336;
            font-weight: bold;
        }

        .resultado-rechazado {
            color: #ff9800;
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .panel-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: var(--space-4);
            }
        }

        @media (max-width: 768px) {
            header {
                padding: var(--space-4) var(--space-5);
                flex-direction: column;
                gap: var(--space-3);
                text-align: center;
            }

            header h1 {
                font-size: 1.5rem;
            }

            main {
                padding: var(--space-6) var(--space-4);
            }

            .welcome-section h2 {
                font-size: 2rem;
            }

            .panel-grid {
                grid-template-columns: 1fr;
            }
            
            .auditoria-table {
                display: block;
                overflow-x: auto;
            }
            
            .auditoria-table th,
            .auditoria-table td {
                white-space: nowrap;
            }
        }

        @media (max-width: 576px) {
            .user-info {
                flex-direction: column;
                gap: var(--space-2);
                padding: var(--space-3);
            }

            .panel-card {
                padding: var(--space-6);
            }
        }

        /* Accesibilidad */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus states mejorados */
        .panel-card:focus-visible,
        header a:focus-visible,
        .btn-auditar:focus-visible {
            outline: 2px solid var(--verde-olivo-500);
            outline-offset: 2px;
        }

        /* Efectos adicionales */
        .floating {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .panel-card:nth-child(1) { animation-delay: 0s; }
        .panel-card:nth-child(2) { animation-delay: 0.2s; }
    </style>
</head>
<body>

    <header>
        <div class="user-welcome">
            <h1>Panel de Administración</h1>
        </div>
        <div class="user-info">
            <span>👨‍💼 <?php echo htmlspecialchars($usuario); ?></span>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </header>

    <main>
        <div class="welcome-section">
            <h2>Bienvenido al Sistema SISCOL</h2>
            <p class="welcome-subtitle">
                Desde aquí puedes gestionar todos los aspectos de tu institución educativa. 
                Selecciona una opción para comenzar.
            </p>
        </div>

        <div class="panel-grid">
            <a href="usuarios.php" class="panel-card floating">
                <span class="panel-icon">👥</span>
                <h3 class="panel-title">Gestionar Usuarios</h3>
                <p class="panel-description">
                    Administra estudiantes, profesores y personal. Crea, edita y gestiona cuentas de usuario.
                </p>
                <div class="panel-action">
                    <span>🚀</span>
                    <span>Acceder</span>
                </div>
            </a>

            <a href="roles.php" class="panel-card floating">
                <span class="panel-icon">🔐</span>
                <h3 class="panel-title">Gestionar Roles</h3>
                <p class="panel-description">
                    Configura permisos y roles de usuarios. Define qué puede hacer cada tipo de usuario.
                </p>
                <div class="panel-action">
                    <span>⚙️</span>
                    <span>Configurar</span>
                </div>
            </a>
        </div>
        
        <!-- Botón de Auditoría -->
        <button type="button" class="btn-auditar" data-bs-toggle="modal" data-bs-target="#modalAuditoria">
            <span>🔍</span>
            <span>Auditar Sistema</span>
        </button>
    </main>

    <!-- Modal de Auditoría -->
    <div class="modal fade" id="modalAuditoria" tabindex="-1" aria-labelledby="modalAuditoriaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--azul-marino-600), var(--azul-marino-700)); color: white;">
                    <h5 class="modal-title" id="modalAuditoriaLabel">🔍 Registro de Auditoría del Sistema</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background: var(--azul-marino-900); color: white;">
                    <div class="table-responsive">
                        <table class="auditoria-table">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Acción</th>
                                    <th>Resultado</th>
                                    <th>Objeto</th>
                                    <th>ID Objeto</th>
                                    <th>Campo</th>
                                    <th>IP</th>
                                    <th>Motivo (si falló)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($auditoria_data)): ?>
                                    <?php foreach ($auditoria_data as $registro): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])); ?></td>
                                            <td><?php echo htmlspecialchars($registro['usuario_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($registro['tipo_usuario'])); ?></td>
                                            <td><?php echo htmlspecialchars($registro['accion']); ?></td>
                                            <td>
                                                <?php if ($registro['resultado'] === 'EXITO'): ?>
                                                    <span class="resultado-exito">✓ Éxito</span>
                                                <?php elseif ($registro['resultado'] === 'FALLIDO'): ?>
                                                    <span class="resultado-fallido">✗ Fallido</span>
                                                <?php else: ?>
                                                    <span class="resultado-rechazado">⚠️ Rechazado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['objeto_afectado']); ?></td>
                                            <td><?php echo $registro['id_objeto'] !== null ? htmlspecialchars($registro['id_objeto']) : '—'; ?></td>
                                            <td><?php echo $registro['campo_modificado'] !== null ? htmlspecialchars($registro['campo_modificado']) : '—'; ?></td>
                                            <td><?php echo htmlspecialchars($registro['ip_origen']); ?></td>
                                            <td><?php echo $registro['motivo_fallo'] !== null ? htmlspecialchars($registro['motivo_fallo']) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 2rem;">No hay registros de auditoría disponibles.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3" style="font-size: 0.85rem; color: #aaa;">
                        <p><strong>Descripción de acciones:</strong></p>
                        <ul style="padding-left: 1.5rem; line-height: 1.4;">
                            <li><strong>LOGIN</strong>: Inicio de sesión exitoso</li>
                            <li><strong>LOGOUT</strong>: Cierre de sesión</li>
                            <li><strong>CREATE/UPDATE/DELETE</strong>: Operaciones CRUD en diferentes módulos</li>
                            <li><strong>INSCRIBIR_CARRERA</strong>: Estudiante se inscribe en una carrera</li>
                            <li><strong>SELECCIONAR_MATERIAS</strong>: Estudiante selecciona materias para cursar</li>
                            <li><strong>INSCRIBIR_FINAL</strong>: Estudiante se inscribe para rendir final</li>
                            <li><strong>REGULARIZAR_ESTUDIANTE</strong>: Gestión del estado académico</li>
                            <li><strong>TOMAR_ASISTENCIA</strong>: Registro de asistencia por parte del profesor</li>
                            <li><strong>ACCESO_PANEL</strong>: Acceso a paneles de usuario</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer" style="background: var(--azul-marino-800);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const panelCards = document.querySelectorAll('.panel-card');
            
            panelCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255,255,255,0.3);
                        transform: scale(0);
                        opacity: 1;
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                        z-index: 1000;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 300);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>

</body>
</html>