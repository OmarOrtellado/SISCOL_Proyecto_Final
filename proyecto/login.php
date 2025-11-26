<?php
session_start();

// Función para redirigir según el rol
function redirigirPorRol($rol) {
    $rutas = [
        "super_usuario" => "panel_admin.php",
        "director"      => "panel_director.php",
        "secretaria"    => "panel_secretaria.php",
        "profesor"      => "panel_profesor.php",
        "estudiante"    => "panel_estudiante.php"
    ];

    if (isset($rutas[$rol])) {
        header("Location: " . $rutas[$rol]);
        exit();
    }
    return false;
}

// Si ya está logueado, redirigir inmediatamente
if (isset($_SESSION["id_usuario"])) {
    if (!redirigirPorRol($_SESSION["rol"])) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
}

require_once "conexion.php";

// Función para registrar en la auditoría
function registrarAuditoria($conn, $id_usuario, $tipo_usuario, $usuario_nombre, $accion, $resultado, $motivo = null, $objeto_afectado = null, $id_objeto = null) {
    $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id();

    $sql_auditoria = "INSERT INTO auditoria (
        id_usuario, tipo_usuario, usuario_nombre, accion, resultado, motivo_fallo,
        objeto_afectado, id_objeto, ip_origen, user_agent, session_id, fecha_hora
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

    $stmt_auditoria = $conn->prepare($sql_auditoria);
    if ($stmt_auditoria) {
        $stmt_auditoria->bind_param(
            "isssssissss",
            $id_usuario,
            $tipo_usuario,
            $usuario_nombre,
            $accion,
            $resultado,
            $motivo,
            $objeto_afectado,
            $id_objeto,
            $ip_origen,
            $user_agent,
            $session_id
        );
        $stmt_auditoria->execute();
        $stmt_auditoria->close();
    }
}

// Inicializar variables
$email = "";
$show_modal = false;
$modal_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($email)) {
        $modal_message = "El correo es obligatorio.";
        $show_modal = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $modal_message = "Ingresa un correo válido.";
        $show_modal = true;
    } elseif (empty($password)) {
        $modal_message = "La contraseña es obligatoria.";
        $show_modal = true;
    } else {
        $conn = conectar();
        if (!$conn) {
            $modal_message = "Error interno. Por favor, inténtalo más tarde.";
            $show_modal = true;
        } else {
            $found = false;
            $user_data = null;
            $accion = "LOGIN";

            $tablas = [
                ['tabla' => 'estudiante',      'id_field' => 'id',               'rol' => 'estudiante'],
                ['tabla' => 'profesores',      'id_field' => 'id_profesor',      'rol' => 'profesor'],
                ['tabla' => 'secretarios',     'id_field' => 'id_secretaria',    'rol' => 'secretaria'],
                ['tabla' => 'directivos',      'id_field' => 'id_direccion',     'rol' => 'director'],
                ['tabla' => 'super_usuario',   'id_field' => 'id_super_usuario', 'rol' => 'super_usuario']
            ];

            foreach ($tablas as $tabla_info) {
                $tabla = $tabla_info['tabla'];
                $id_field = $tabla_info['id_field'];
                $rol_nombre = $tabla_info['rol'];

                $sql = "SELECT `$id_field` AS id, nombre, apellido, email, password_hash, activo 
                        FROM `$tabla` 
                        WHERE email = ? LIMIT 1";

                $stmt = $conn->prepare($sql);
                if (!$stmt) continue;

                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $row = $result->fetch_assoc();
                    if (isset($row["activo"]) && $row["activo"] == 1 && password_verify($password, $row["password_hash"])) {
                        $user_data = [
                            'id' => $row['id'],
                            'nombre' => $row['nombre'],
                            'apellido' => $row['apellido'],
                            'email' => $row['email'],
                            'rol' => $rol_nombre
                        ];
                        $found = true;
                        $stmt->close();
                        registrarAuditoria($conn, $user_data["id"], $rol_nombre, $user_data["nombre"] . " " . $user_data["apellido"], $accion, "EXITO", null, 'sesion', $user_data["id"]);
                        break;
                    } else {
                        $usuario_nombre = $row['nombre'] . " " . $row['apellido'];
                        $motivo = isset($row["activo"]) && $row["activo"] != 1 ? "Cuenta inactiva" : "Contraseña incorrecta";
                        registrarAuditoria($conn, $row['id'], $rol_nombre, $usuario_nombre, $accion, "FALLIDO", $motivo, 'sesion', $row['id']);
                    }
                }
                $stmt->close();
            }

            $conn->close();

            if ($found && $user_data) {
                $_SESSION["usuario"] = $user_data["nombre"] . " " . $user_data["apellido"];
                $_SESSION["rol"] = $user_data["rol"];
                $_SESSION["id_usuario"] = $user_data["id"];
                redirigirPorRol($user_data["rol"]);
            } else {
                $modal_message = "Credenciales incorrectas o cuenta inactiva.";
                $show_modal = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SISCOL - Acceso</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root {
      --azul-marino-500: #1E3A5F;
      --azul-marino-600: #1a3354;
      --azul-marino-700: #162b45;
      --azul-marino-800: #122336;
      --azul-marino-900: #0d1a28;
      --verde-olivo-100: #ebede3;
      --verde-olivo-500: #556B2F;
      --verde-olivo-600: #4a5e29;
      --verde-olivo-700: #3f5023;
      --verde-olivo-800: #34421d;
      --space-2: 0.5rem;
      --space-4: 1rem;
      --space-6: 1.5rem;
      --space-8: 2rem;
      --space-10: 2.5rem;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, var(--azul-marino-900), var(--verde-olivo-800));
      color: white;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
      position: relative;
    }

    body::before {
      content: '';
      position: absolute;
      inset: 0;
      background: 
        radial-gradient(circle at 20% 80%, rgba(85, 107, 47, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(30, 58, 95, 0.1) 0%, transparent 50%);
      pointer-events: none;
      z-index: 1;
    }

    .particles {
      position: absolute;
      inset: 0;
      overflow: hidden;
      z-index: 1;
    }

    .particle {
      position: absolute;
      font-size: 2rem;
      opacity: 0.6;
      animation: float 20s infinite linear;
      user-select: none;
      pointer-events: none;
    }

    @keyframes float {
      0% { 
        opacity: 0; 
        transform: translateY(-10vh) rotate(0deg); 
      }
      10% { opacity: 0.6; }
      90% { opacity: 0.6; }
      100% { 
        opacity: 0; 
        transform: translateY(110vh) rotate(360deg); 
      }
    }

    .login-container {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
      padding: var(--space-10);
      border-radius: 20px;
      width: 100%;
      max-width: 420px;
      position: relative;
      z-index: 10;
    }

    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--verde-olivo-500), var(--azul-marino-500));
    }

    h2 {
      text-align: center;
      margin-bottom: var(--space-8);
      color: #fff;
      font-size: 2rem;
      font-weight: 700;
      text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    .form-label {
      color: rgba(255,255,255,0.9);
      font-weight: 600;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: var(--space-2);
      display: block;
    }

    .form-control {
      width: 100%;
      padding: 1rem 1.25rem;
      background: rgba(255,255,255,0.08);
      border: 2px solid rgba(255,255,255,0.2);
      border-radius: 12px;
      color: white;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .form-control::placeholder {
      color: rgba(255,255,255,0.5);
    }

    .form-control:focus {
      outline: none;
      background: rgba(255,255,255,0.12);
      border-color: var(--verde-olivo-500);
      box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.2);
      color: white;
    }

    .btn-login {
      background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
      border: none;
      width: 100%;
      padding: 1rem;
      font-weight: 700;
      font-size: 1rem;
      border-radius: 12px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      box-shadow: 0 4px 15px rgba(85, 107, 47, 0.3);
    }

    .btn-login:hover {
      background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(85, 107, 47, 0.4);
    }

    .forgot-password {
      display: block;
      margin-top: var(--space-6);
      text-align: center;
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .forgot-password:hover {
      color: #ffffff;
    }

    .modal-content {
      background: rgba(18, 35, 54, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(85, 107, 47, 0.3);
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
      color: rgba(255, 255, 255, 0.95);
    }

    .modal-header {
      background: linear-gradient(90deg, var(--verde-olivo-600), var(--azul-marino-600)) !important;
      border-bottom: none !important;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
      padding: 1.1rem;
    }

    .modal-title {
      font-weight: 600;
      font-size: 1.1rem;
      letter-spacing: 0.5px;
    }

    .btn-close {
      filter: invert(1) brightness(1.2);
    }

    .modal-body {
      padding: 1.25rem 1.5rem;
      color: rgba(255, 255, 255, 0.92) !important;
      font-size: 1rem;
      line-height: 1.5;
    }

    .modal-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding: 1rem 1.5rem;
    }

    .modal-footer .btn-primary {
      background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
      border: none;
      padding: 0.5rem 1.2rem;
      font-weight: 600;
      letter-spacing: 0.5px;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(85, 107, 47, 0.3);
    }

    .modal-footer .btn-primary:hover {
      background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
      transform: translateY(-1px);
      box-shadow: 0 6px 14px rgba(85, 107, 47, 0.4);
    }

    @media (max-width: 480px) {
      .login-container {
        margin: var(--space-4);
        padding: var(--space-6);
        max-width: none;
      }
      h2 { font-size: 1.5rem; }
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
      .particles { display: none; }
    }
  </style>
</head>
<body>

  <div class="particles"></div>

  <div class="login-container">
    <h2>Acceso a SISCOL</h2>
    <form method="POST" id="loginForm">
      <div class="mb-4">
        <label for="email" class="form-label">Correo Electrónico</label>
        <input 
          type="email" 
          class="form-control"
          id="email" 
          name="email" 
          value="<?= htmlspecialchars($email) ?>" 
          placeholder="usuario@ejemplo.com" 
          required
        >
      </div>

      <div class="mb-4">
        <label for="password" class="form-label">Contraseña</label>
        <input 
          type="password" 
          class="form-control"
          id="password" 
          name="password" 
          placeholder="••••••••" 
          required
        >
      </div>

      <button type="submit" class="btn-login">Ingresar</button>
    </form>

    <a href="recuperar_contraseña.html" class="forgot-password">¿Olvidaste tu contraseña?</a>
  </div>

  <!-- Modal de error -->
  <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Acceso denegado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <?= htmlspecialchars(!empty(trim($modal_message)) ? $modal_message : 'Error desconocido.') ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const particles = document.querySelector('.particles');
      
      // Íconos educativos variados
      const educationIcons = ['📚', '📖', '✏️', '📝', '🎓', '📐', '🖊️', '📊', '🔬', '🎨', '📙', '📕', '🖍️', '✂️', '📌'];
      
      // Crear 20 íconos cayendo
      for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        const randomIcon = educationIcons[Math.floor(Math.random() * educationIcons.length)];
        particle.textContent = randomIcon;
        particle.style.cssText = `
          left: ${Math.random() * 100}%;
          animation-delay: ${Math.random() * 8}s;
          animation-duration: ${8 + Math.random() * 7}s;
          font-size: ${1.5 + Math.random() * 1.5}rem;
        `;
        particles.appendChild(particle);
      }

      // Mostrar modal si hay mensaje
      <?php if (!empty($show_modal) && !empty(trim($modal_message))): ?>
        const myModal = new bootstrap.Modal(document.getElementById('messageModal'));
        myModal.show();
      <?php endif; ?>
    });
  </script>

</body>
</html>