<?php
session_start();
require_once "conexion.php";

// Verificar que es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

$conn = conectar();

// Obtener todos los usuarios (estudiantes, profesores, secretarios, directivos, super_usuario) con su rol
// Se usa una serie de UNION ALL para combinar datos de diferentes tablas
// Se fuerza un COLLATION común para evitar el error de mezcla ilegal
$sql = "
    SELECT id, dni, nombre, apellido, email, telefono, activo, 'estudiante' as tabla_origen, id_carrera
    FROM estudiante
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT id_profesor as id, dni, nombre, apellido, email, telefono, activo, 'profesores' as tabla_origen, NULL as id_carrera
    FROM profesores
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT id_secretaria as id, dni, nombre, apellido, email, telefono, activo, 'secretarios' as tabla_origen, NULL as id_carrera
    FROM secretarios
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT id_direccion as id, dni, nombre, apellido, email, telefono, activo, 'directivos' as tabla_origen, NULL as id_carrera
    FROM directivos
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT id_super_usuario as id, dni, nombre, apellido, email, telefono, activo, 'super_usuario' as tabla_origen, NULL as id_carrera
    FROM super_usuario
    WHERE activo = 1 OR activo = 0
    ORDER BY tabla_origen, apellido, nombre
";
// OPCIÓN 2: Forzar COLLATION en la consulta UNION
// Si la opción 1 no funciona, puedes forzar el collation en cada SELECT de la UNION.
// Esto es más robusto si hay diferencias de collation específicas.
$sql = "
    SELECT 
        CAST(id AS CHAR) COLLATE utf8mb4_unicode_ci AS id,
        CAST(dni AS CHAR) COLLATE utf8mb4_unicode_ci AS dni,
        CAST(nombre AS CHAR) COLLATE utf8mb4_unicode_ci AS nombre,
        CAST(apellido AS CHAR) COLLATE utf8mb4_unicode_ci AS apellido,
        CAST(email AS CHAR) COLLATE utf8mb4_unicode_ci AS email,
        CAST(telefono AS CHAR) COLLATE utf8mb4_unicode_ci AS telefono,
        CAST(activo AS UNSIGNED) AS activo,
        'estudiante' as tabla_origen,
        CAST(id_carrera AS UNSIGNED) AS id_carrera
    FROM estudiante
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT 
        CAST(id_profesor AS CHAR) COLLATE utf8mb4_unicode_ci AS id,
        CAST(dni AS CHAR) COLLATE utf8mb4_unicode_ci AS dni,
        CAST(nombre AS CHAR) COLLATE utf8mb4_unicode_ci AS nombre,
        CAST(apellido AS CHAR) COLLATE utf8mb4_unicode_ci AS apellido,
        CAST(email AS CHAR) COLLATE utf8mb4_unicode_ci AS email,
        CAST(telefono AS CHAR) COLLATE utf8mb4_unicode_ci AS telefono,
        CAST(activo AS UNSIGNED) AS activo,
        'profesores' as tabla_origen,
        NULL AS id_carrera
    FROM profesores
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT 
        CAST(id_secretaria AS CHAR) COLLATE utf8mb4_unicode_ci AS id,
        CAST(dni AS CHAR) COLLATE utf8mb4_unicode_ci AS dni,
        CAST(nombre AS CHAR) COLLATE utf8mb4_unicode_ci AS nombre,
        CAST(apellido AS CHAR) COLLATE utf8mb4_unicode_ci AS apellido,
        CAST(email AS CHAR) COLLATE utf8mb4_unicode_ci AS email,
        CAST(telefono AS CHAR) COLLATE utf8mb4_unicode_ci AS telefono,
        CAST(activo AS UNSIGNED) AS activo,
        'secretarios' as tabla_origen,
        NULL AS id_carrera
    FROM secretarios
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT 
        CAST(id_direccion AS CHAR) COLLATE utf8mb4_unicode_ci AS id,
        CAST(dni AS CHAR) COLLATE utf8mb4_unicode_ci AS dni,
        CAST(nombre AS CHAR) COLLATE utf8mb4_unicode_ci AS nombre,
        CAST(apellido AS CHAR) COLLATE utf8mb4_unicode_ci AS apellido,
        CAST(email AS CHAR) COLLATE utf8mb4_unicode_ci AS email,
        CAST(telefono AS CHAR) COLLATE utf8mb4_unicode_ci AS telefono,
        CAST(activo AS UNSIGNED) AS activo,
        'directivos' as tabla_origen,
        NULL AS id_carrera
    FROM directivos
    WHERE activo = 1 OR activo = 0
    UNION ALL
    SELECT 
        CAST(id_super_usuario AS CHAR) COLLATE utf8mb4_unicode_ci AS id,
        CAST(dni AS CHAR) COLLATE utf8mb4_unicode_ci AS dni,
        CAST(nombre AS CHAR) COLLATE utf8mb4_unicode_ci AS nombre,
        CAST(apellido AS CHAR) COLLATE utf8mb4_unicode_ci AS apellido,
        CAST(email AS CHAR) COLLATE utf8mb4_unicode_ci AS email,
        CAST(telefono AS CHAR) COLLATE utf8mb4_unicode_ci AS telefono,
        CAST(activo AS UNSIGNED) AS activo,
        'super_usuario' as tabla_origen,
        NULL AS id_carrera
    FROM super_usuario
    WHERE activo = 1 OR activo = 0
    ORDER BY tabla_origen, apellido, nombre
";

$result = $conn->query($sql);

// Obtener todos los roles para el filtro
$roles_sql = "SELECT nombre FROM roles ORDER BY nombre ASC";
$roles_result = $conn->query($roles_sql);
$roles = [];
while($r = $roles_result->fetch_assoc()) {
    $roles[] = $r['nombre'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios - SISCOL</title>
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
}

body { 
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
  background: linear-gradient(135deg, var(--azul-marino-900), var(--azul-marino-700));
  color: white; 
  min-height: 100vh; 
  padding: var(--space-8);
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
    radial-gradient(circle at 85% 15%, rgba(85, 107, 47, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 15% 85%, rgba(30, 58, 95, 0.1) 0%, transparent 50%);
  pointer-events: none;
  z-index: 1;
}

/* Header mejorado */
.header-section {
  position: relative;
  z-index: 10;
  text-align: center;
  margin-bottom: var(--space-8);
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

h1 { 
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: var(--space-6);
  background: linear-gradient(135deg, #ffffff, rgba(255,255,255,0.8));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

/* Botones de acción mejorados */
.action-buttons {
  display: flex;
  justify-content: center;
  gap: var(--space-4);
  margin-bottom: var(--space-8);
  flex-wrap: wrap;
  position: relative;
  z-index: 10;
  animation: fadeInUp 0.8s ease-out 0.2s both;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.btn-crear { 
  background: linear-gradient(135deg, var(--verde-olivo-500), var(--verde-olivo-600));
  border: none; 
  color: white; 
  padding: var(--space-4) var(--space-6); 
  border-radius: 12px; 
  font-weight: 700;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  box-shadow: 0 4px 15px rgba(85, 107, 47, 0.3);
  position: relative;
  overflow: hidden;
}

.btn-crear::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.5s;
}

.btn-crear:hover { 
  background: linear-gradient(135deg, var(--verde-olivo-600), var(--verde-olivo-700));
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(85, 107, 47, 0.4);
  color: white;
  text-decoration: none;
}

.btn-crear:hover::before {
  left: 100%;
}

/* Sección de filtros mejorada */
.filters-section {
  position: relative;
  z-index: 10;
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: var(--space-6);
  margin-bottom: var(--space-8);
  animation: fadeInUp 0.8s ease-out 0.4s both;
}

.filters { 
  display: flex; 
  gap: var(--space-4); 
  flex-wrap: wrap; 
  justify-content: center;
  align-items: center;
}

.filters input, .filters select { 
  padding: var(--space-3) var(--space-4); 
  border-radius: 10px; 
  border: 2px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.08);
  color: white;
  font-size: 0.9rem;
  transition: all 0.3s ease;
  min-width: 200px;
}

.filters input::placeholder {
  color: rgba(255, 255, 255, 0.5);
}

.filters input:focus, .filters select:focus {
  outline: none;
  background: rgba(255, 255, 255, 0.12);
  border-color: var(--verde-olivo-500);
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.2);
  transform: translateY(-1px);
}

.filters select option {
  background: var(--azul-marino-800);
  color: white;
}

.filter-label {
  font-weight: 600;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: var(--space-2);
  display: block;
  color: rgba(255, 255, 255, 0.9);
}

/* Contenedor de tabla mejorado con mejor contraste */
.table-container { 
  background: linear-gradient(145deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.9) 100%);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px; 
  padding: 0; 
  overflow: hidden;
  position: relative;
  z-index: 10;
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
  animation: fadeInUp 0.8s ease-out 0.6s both;
}

.table {
  margin-bottom: 0;
  background: transparent;
}

.table thead th {
  background: linear-gradient(135deg, var(--azul-marino-700), var(--azul-marino-600));
  color: #ffffff;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
  border: none;
  padding: var(--space-5) var(--space-4);
  text-align: center;
  vertical-align: middle;
  position: relative;
}

.table thead th:first-child {
  border-top-left-radius: 20px;
}

.table thead th:last-child {
  border-top-right-radius: 20px;
}

.table tbody tr {
  border: none;
  transition: all 0.2s ease;
  background: rgba(255,255,255,0.85);
}

.table tbody tr:hover {
  background: rgba(255,255,255,0.95);
  transform: scale(1.002);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.table tbody td {
  border: none;
  border-bottom: 1px solid rgba(0,0,0,0.1);
  padding: var(--space-4);
  vertical-align: middle;
  text-align: center;
  color: #1a1a1a; /* Color negro para máximo contraste */
  font-weight: 500;
}

.table tbody tr:nth-child(even) {
  background: rgba(248,249,250,0.9); /* Gris muy claro alternado */
}

.table tbody tr:nth-child(even):hover {
  background: rgba(255,255,255,0.95);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

/* Botones de acción en tabla */
.btn-group-actions {
  display: flex;
  gap: var(--space-2);
  justify-content: center;
}

.btn-table-action {
  padding: var(--space-2) var(--space-3);
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  border: none;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  text-decoration: none;
  min-width: 70px;
  justify-content: center;
}

.btn-editar {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-editar:hover {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
  color: white;
  text-decoration: none;
}

.btn-eliminar {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-eliminar:hover {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
  color: white;
  text-decoration: none;
}

/* Estados de usuario con mejor contraste */
.estado-badge {
  padding: var(--space-1) var(--space-3);
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.activo { 
  background: rgba(34, 197, 94, 0.15);
  color: #15803d; /* Verde más oscuro */
  border: 1px solid rgba(34, 197, 94, 0.4);
}

.inactivo { 
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626; /* Rojo más oscuro */
  border: 1px solid rgba(239, 68, 68, 0.4);
}

/* Información del usuario con mejor contraste */
.user-name {
  font-weight: 600;
  color: #1a1a1a; /* Negro sólido */
}

.user-email {
  font-size: 0.85rem;
  color: #4a4a4a; /* Gris oscuro */
}

/* Ajustes para el ID y datos importantes */
.table tbody td:first-child strong {
  color: var(--azul-marino-700);
  font-weight: 700;
}

/* Responsive Design */
@media (max-width: 992px) {
  .table-container {
    overflow-x: auto;
  }
  
  .table {
    min-width: 800px;
  }
}

@media (max-width: 768px) {
  body {
    padding: var(--space-4);
  }
  
  h1 {
    font-size: 2rem;
  }
  
  .action-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .filters {
    flex-direction: column;
  }
  
  .filters input, .filters select {
    min-width: 100%;
  }
  
  .btn-table-action {
    padding: var(--space-1) var(--space-2);
    font-size: 0.7rem;
    min-width: 60px;
  }
  
  .table tbody td {
    font-size: 0.85rem;
    color: #1a1a1a;
  }
  
  .user-name {
    font-size: 0.85rem;
  }
  
  .user-email {
    font-size: 0.8rem;
  }
}

@media (max-width: 576px) {
  .table thead th, .table tbody td {
    padding: var(--space-2);
    font-size: 0.8rem;
  }
  
  .btn-group-actions {
    flex-direction: column;
    gap: var(--space-1);
  }
}

/* Estados de carga */
.loading-table {
  position: relative;
  pointer-events: none;
  opacity: 0.7;
}

.loading-table::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 40px;
  height: 40px;
  margin: -20px 0 0 -20px;
  border: 4px solid var(--verde-olivo-500);
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  z-index: 1000;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Contador de resultados */
.results-counter {
  text-align: center;
  margin-bottom: var(--space-4);
  font-size: 0.9rem;
  opacity: 0.8;
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
.btn-crear:focus-visible,
.btn-table-action:focus-visible,
.filters input:focus-visible,
.filters select:focus-visible {
  outline: 2px solid var(--verde-olivo-500);
  outline-offset: 2px;
}
</style>
</head>
<body>

<div class="header-section">
  <h1>Gestión de Usuarios</h1>
  <p style="font-size: 1.1rem; opacity: 0.8; margin-bottom: 0;">Sistema de Control Institucional</p>
</div>

<div class="action-buttons">
  <a href="crear_usuario.php" class="btn btn-crear">
    <span>➕</span>
    <span>Crear Nuevo Usuario</span>
  </a>
  <a href="panel_admin.php" class="btn btn-crear" style="background: linear-gradient(135deg, var(--azul-marino-500), var(--azul-marino-600));">
    <span>🔙</span>
    <span>Volver al Panel</span>
  </a>
</div>

<div class="filters-section">
  <h3 style="text-align: center; margin-bottom: var(--space-4); font-size: 1.1rem; opacity: 0.9;">Filtros de Búsqueda</h3>
  <div class="filters">
    <div>
      <label class="filter-label" for="buscarNombre">Buscar Usuario</label>
      <input type="text" id="buscarNombre" placeholder="Buscar por nombre o email...">
    </div>
    <div>
      <label class="filter-label" for="filtroRol">Filtrar por Rol</label>
      <select id="filtroRol">
        <option value="Todos">Todos los roles</option>
        <?php foreach($roles as $rol): ?>
          <option value="<?php echo htmlspecialchars($rol); ?>">
            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($rol))); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="results-counter" id="resultsCounter">
    Mostrando <span id="visibleRows">0</span> de <span id="totalRows">0</span> usuarios
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover" id="tablaUsuarios">
      <thead>
        <tr>
          <th>ID</th>
          <th>DNI</th>
          <th>Usuario</th>
          <th>Email</th>
          <th>Teléfono</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php $contador = 0; ?>
        <?php while($row = $result->fetch_assoc()): ?>
        <?php $contador++; ?>
        <tr data-usuario="<?php echo strtolower($row['nombre'] . ' ' . $row['apellido'] . ' ' . $row['email']); ?>" data-rol="<?php echo $row['tabla_origen']; ?>">
          <td><strong><?php echo $row['id']; ?></strong></td>
          <td><?php echo htmlspecialchars($row['dni']); ?></td>
          <td>
            <div class="user-name"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></div>
          </td>
          <td>
            <div class="user-email"><?php echo htmlspecialchars($row['email']); ?></div>
          </td>
          <td><?php echo htmlspecialchars($row['telefono']); ?></td>
          <td>
            <span class="tag" style="background: rgba(30,58,95,0.15); color: #1E3A5F; padding: 4px 8px; border-radius: 6px; font-weight: 600; border: 1px solid rgba(30,58,95,0.3);">
              <?php 
              // Mapear la tabla origen al nombre del rol
              $rol_nombre = 'Desconocido';
              switch ($row['tabla_origen']) {
                  case 'estudiante':
                      $rol_nombre = 'Estudiante';
                      break;
                  case 'profesores':
                      $rol_nombre = 'Profesor';
                      break;
                  case 'secretarios':
                      $rol_nombre = 'Secretario';
                      break;
                  case 'directivos':
                      $rol_nombre = 'Directivo';
                      break;
                  case 'super_usuario':
                      $rol_nombre = 'Super Usuario';
                      break;
              }
              echo htmlspecialchars(ucfirst($rol_nombre)); 
              ?>
            </span>
          </td>
          <td>
            <span class="estado-badge <?php echo $row['activo'] ? 'activo' : 'inactivo'; ?>">
              <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
            </span>
          </td>
          <td>
            <div class="btn-group-actions">
              <a href="editar_usuario.php?id=<?php echo $row['id']; ?>&tabla=<?php echo $row['tabla_origen']; ?>" 
                 class="btn-table-action btn-editar" 
                 title="Editar usuario">
                ✏️ Editar
              </a>
              <a href="eliminar_usuario.php?id=<?php echo $row['id']; ?>&tabla=<?php echo $row['tabla_origen']; ?>" 
                 class="btn-table-action btn-eliminar" 
                 onclick="return confirm('¿Estás seguro de eliminar este usuario?');"
                 title="Eliminar usuario">
                🗑️ Eliminar
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Elementos del DOM
  const inputBuscar = document.getElementById('buscarNombre');
  const selectRol = document.getElementById('filtroRol');
  const tabla = document.getElementById('tablaUsuarios').getElementsByTagName('tbody')[0];
  const resultsCounter = document.getElementById('resultsCounter');
  const visibleRows = document.getElementById('visibleRows');
  const totalRows = document.getElementById('totalRows');
  
  // Contar filas totales
  const totalUsuarios = tabla.rows.length;
  totalRows.textContent = totalUsuarios;
  
  // Función de filtrado mejorada
  function filtrarTabla() {
    const texto = inputBuscar.value.toLowerCase().trim();
    const rolSeleccionado = selectRol.value;
    let filasVisibles = 0;
    
    // Agregar clase de loading
    tabla.parentElement.parentElement.classList.add('loading-table');
    
    setTimeout(() => {
      for (let fila of tabla.rows) {
        const datosUsuario = fila.dataset.usuario || '';
        const rolUsuario = fila.dataset.rol || ''; // Ahora es la tabla origen
        
        const coincideTexto = texto === '' || datosUsuario.includes(texto);
        const coincideRol = (rolSeleccionado === "Todos") || (rolUsuario === rolSeleccionado);
        
        if (coincideTexto && coincideRol) {
          fila.style.display = "";
          filasVisibles++;
          // Animación de entrada sutil
          fila.style.animation = 'fadeInUp 0.3s ease-out';
        } else {
          fila.style.display = "none";
        }
      }
      
      // Actualizar contador
      visibleRows.textContent = filasVisibles;
      
      // Remover clase de loading
      tabla.parentElement.parentElement.classList.remove('loading-table');
    }, 150);
  }
  
  // Event listeners con debounce para mejor rendimiento
  let timeoutId;
  
  inputBuscar.addEventListener('input', function() {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(filtrarTabla, 300);
  });
  
  selectRol.addEventListener('change', filtrarTabla);
  
  // Inicializar contador
  filtrarTabla();
  
  // Mejorar experiencia de hover en filas
  const filas = tabla.querySelectorAll('tr');
  filas.forEach(fila => {
    fila.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.002)';
      this.style.zIndex = '1';
    });
    
    fila.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
      this.style.zIndex = 'auto';
    });
  });
  
  // Confirmación mejorada para eliminar
  const botonesEliminar = document.querySelectorAll('.btn-eliminar');
  botonesEliminar.forEach(boton => {
    boton.addEventListener('click', function(e) {
      e.preventDefault();
      
      const nombreUsuario = this.closest('tr').querySelector('.user-name').textContent;
      
      if (confirm(`¿Estás seguro de que quieres eliminar al usuario "${nombreUsuario}"?\n\nEsta acción no se puede deshacer.`)) {
        // Agregar efecto de loading al botón
        this.innerHTML = '⏳ Eliminando...';
        this.style.pointerEvents = 'none';
        
        // Redirigir después de un breve delay
        setTimeout(() => {
          window.location.href = this.href;
        }, 500);
      }
    });
  });
  
  // Efecto ripple para botones
  const botones = document.querySelectorAll('.btn-crear, .btn-table-action');
  botones.forEach(boton => {
    boton.addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';
      ripple.style.position = 'absolute';
      ripple.style.borderRadius = '50%';
      ripple.style.background = 'rgba(255,255,255,0.6)';
      ripple.style.transform = 'scale(0)';
      ripple.style.animation = 'ripple 0.6s linear';
      ripple.style.pointerEvents = 'none';
      
      this.style.position = 'relative';
      this.style.overflow = 'hidden';
      this.appendChild(ripple);
      
      setTimeout(() => {
        ripple.remove();
      }, 600);
    });
  });
  
  // CSS para animaciones adicionales
  const style = document.createElement('style');
  style.textContent = `
    @keyframes ripple {
      to {
        transform: scale(4);
        opacity: 0;
      }
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0.5;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  `;
  document.head.appendChild(style);
});
</script>

</body>
</html>

<?php $conn->close(); ?>