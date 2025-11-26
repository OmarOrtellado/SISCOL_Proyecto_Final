<?php
session_start(); // Iniciar sesión para verificar permisos
require_once "conexion.php";

// Verificar que es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.php");
    exit();
}

// Obtener conexión
$conexion = conectar();

// Inicializar mensaje
$mensaje = "";

// Agregar nuevo rol si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    // Usar consultas preparadas para mayor seguridad (buena práctica)
    $stmt = $conexion->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)");
    $stmt->bind_param("ss", $_POST['nombre'], $_POST['descripcion']);
    
    if ($stmt->execute()) {
        $nuevo_id = $conexion->insert_id; // Obtener el ID del nuevo rol
        $mensaje = "Rol agregado correctamente.";
        // Registrar éxito en auditoría
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        // Registrar evento de auditoría
        $sql_audit = "INSERT INTO auditoria (
            id_usuario, tipo_usuario, usuario_nombre, accion, resultado, 
            objeto_afectado, id_objeto, campo_modificado, valor_anterior, valor_nuevo,
            ip_origen, user_agent, session_id, fecha_hora
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

        $stmt_audit = $conexion->prepare($sql_audit);
        if ($stmt_audit) {
            $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $session_id = session_id();

            // Cadena de tipos: i s s s s s i s s s s s s s (14 caracteres)
            $stmt_audit->bind_param(
                "isssssisssssss",
                $id_usuario_sesion,
                $tipo_usuario_sesion,
                $usuario_nombre_sesion,
                'CREAR_ROL', // Acción
                'EXITO', // Resultado
                'roles', // Objeto afectado
                $nuevo_id, // ID del nuevo rol
                'nombre', // Campo modificado
                null, // Valor anterior
                $_POST['nombre'], // Valor nuevo
                $ip_origen,
                $user_agent,
                $session_id
            );
            $stmt_audit->execute();
            $stmt_audit->close();
        }
    } else {
        $mensaje = "Error al agregar el rol: " . $stmt->error;
        // Registrar intento fallido en auditoría
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        $sql_audit_fail = "INSERT INTO auditoria (
            id_usuario, tipo_usuario, usuario_nombre, accion, resultado, motivo_fallo,
            objeto_afectado, ip_origen, user_agent, session_id, fecha_hora
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

        $stmt_audit_fail = $conexion->prepare($sql_audit_fail);
        if ($stmt_audit_fail) {
            $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $session_id = session_id();

            $stmt_audit_fail->bind_param(
                "issssssssss",
                $id_usuario_sesion,
                $tipo_usuario_sesion,
                $usuario_nombre_sesion,
                'CREAR_ROL', // Acción
                'FALLIDO', // Resultado
                $stmt->error, // Motivo del fallo
                'roles', // Objeto afectado
                $ip_origen,
                $user_agent,
                $session_id
            );
            $stmt_audit_fail->execute();
            $stmt_audit_fail->close();
        }
    }
    $stmt->close();
}

// Obtener todos los roles
$result = $conexion->query("SELECT * FROM roles ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Roles - SISCOL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --azul-marino-900: #0d1a28;
            --azul-marino-700: #162b45;
            --verde-olivo-500: #556B2F;
            --verde-olivo-600: #4a5e29;
            --verde-olivo-300: #8fa34a;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--azul-marino-900), var(--azul-marino-700));
            color: white;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        header {
            background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-500));
            padding: var(--space-6) var(--space-8);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }
        
        /* Botón Volver */
        .back-button {
            color: white;
            text-decoration: none;
            padding: var(--space-3) var(--space-4);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: background 0.3s ease;
            font-weight: 600;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .container {
            padding-top: var(--space-8) !important;
            padding-bottom: var(--space-8) !important;
        }

        /* Estilos de Card para tema oscuro */
        .card {
            background-color: var(--azul-marino-700); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .card-body h2 {
            color: white;
        }

        /* Estilos de Tabla para tema oscuro */
        .table {
            /* Color de fondo para todas las filas */
            --bs-table-bg: var(--azul-marino-700);
            /* IGUALAMOS EL COLOR STRIPED al color base para que sea uniforme */
            --bs-table-striped-bg: var(--azul-marino-700); 
            --bs-table-color: white;
            --bs-table-border-color: rgba(255, 255, 255, 0.1);
        }

        .table thead th {
            background-color: var(--azul-marino-900);
            color: var(--verde-olivo-300);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        /* REGLA AGREGADA: FUERZA EL COLOR BLANCO EN EL CONTENIDO DE LA TABLA */
        .table tbody td {
            color: white; 
        }

        /* Regla de HOVER para mantener el resaltado cuando el mouse pasa por encima */
        .table-striped > tbody > tr:hover, 
        .table-striped > tbody > tr:hover td {
            background-color: var(--azul-marino-900) !important; /* Un color ligeramente más oscuro */
        }
        
        /* Ajuste de formulario */
        .form-control {
            background-color: var(--azul-marino-900);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-control:focus {
            background-color: var(--azul-marino-900);
            color: white;
            border-color: var(--verde-olivo-300);
            box-shadow: 0 0 0 0.25rem rgba(143, 163, 74, 0.25); 
        }

        .form-label {
            color: white;
        }
        
        .alert-info {
            --bs-alert-bg: #1e3a5a;
            --bs-alert-color: #90b8f0;
            --bs-alert-border-color: #1e3a5a;
        }
    </style>
</head>
<body>

<header>
    <h2>Gestionar Roles</h2>
    <a href="panel_admin.php" class="back-button">
        ⬅️ Volver
    </a>
</header>
<div class="container">
    <h2 class="mb-4 d-none">Gestionar Roles</h2> 
    <?php if (!empty($mensaje)) : ?>
        <div class="alert alert-info mt-4"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title text-light mb-4">Agregar Nuevo Rol</h5>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre del Rol</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea name="descripcion" id="descripcion" class="form-control"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Agregar Rol</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title text-light mb-4">Roles Existentes</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Creado En</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($rol = $result->fetch_assoc()) : ?>
                            <tr>
                                <td><?= $rol['id'] ?></td>
                                <td><?= htmlspecialchars($rol['nombre']) ?></td>
                                <td><?= htmlspecialchars($rol['descripcion']) ?></td>
                                <td><?= $rol['creado_en'] ?></td>
                                <td>
                                    <a href="editar_rol.php?id=<?= $rol['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="eliminar_rol.php?id=<?= $rol['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este rol?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>