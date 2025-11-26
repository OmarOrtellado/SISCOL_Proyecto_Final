<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "profesor") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();
$id_usuario = $_SESSION["id_usuario"];

// Obtener materias asignadas al profesor a través de la tabla 'asignaciones'
// Se une con la tabla 'materias' para obtener detalles y con 'carreras' para mostrar la carrera
$sql = "SELECT m.id, m.nombre, m.año, c.nombre AS nombre_carrera,
               (SELECT COUNT(*) FROM analitico a WHERE a.id_materia = m.id AND a.activo = 1) as total_estudiantes
        FROM asignaciones a
        JOIN materias m ON a.id_materia = m.id
        JOIN carreras c ON m.id_carrera = c.id_carrera
        WHERE a.id_profesor = ? AND a.activo = 1 AND m.activo = 1
        ORDER BY c.nombre, m.año, m.nombre";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$materias = [];
while($row = $result->fetch_assoc()) {
    $materias[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Materias - SISCOL</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --azul-marino: #1a365d;
      --azul-marino-claro: #2c5282;
      --verde-olivo: #556b2f;
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
      padding: 20px;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      background: white;
      border-radius: 15px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, var(--azul-marino) 0%, var(--verde-olivo) 100%);
      color: white;
      padding: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }

    .header h1 {
      font-size: 28px;
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 0;
    }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-volver {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      backdrop-filter: blur(10px);
    }

    .btn-volver:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    .content {
      padding: 30px;
    }

    .search-container {
      margin-bottom: 25px;
      position: relative;
    }

    .search-container input {
      width: 100%;
      padding: 12px 45px 12px 15px;
      border: 2px solid var(--gris-medio);
      border-radius: 25px;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .search-container input:focus {
      outline: none;
      border-color: var(--azul-marino);
      box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
    }

    .search-icon {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }

    .materias-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 25px;
    }

    .materia-card {
      background: var(--blanco);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .materia-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      border-color: var(--azul-marino);
    }

    .card-header {
      background: linear-gradient(135deg, var(--azul-marino), var(--azul-marino-claro));
      color: white;
      padding: 20px;
      position: relative;
    }

    .card-header h3 {
      font-size: 18px;
      margin: 0 0 8px 0;
      font-weight: 600;
    }

    .card-info {
      display: flex;
      gap: 15px;
      font-size: 13px;
      opacity: 0.95;
      flex-wrap: wrap;
    }

    .info-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .card-body {
      padding: 20px;
    }

    .stat-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px;
      background: var(--gris-claro);
      border-radius: 8px;
      margin-bottom: 15px;
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--verde-olivo), var(--verde-olivo-claro));
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .stat-info {
      flex: 1;
    }

    .stat-label {
      font-size: 12px;
      color: var(--verde-olivo);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-value {
      font-size: 20px;
      font-weight: 700;
      color: var(--negro);
    }

    .card-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 10px;
    }

    .btn-action {
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      text-align: center;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      border: 2px solid transparent;
    }

    .btn-ver {
      background: var(--azul-marino);
      color: white;
    }

    .btn-ver:hover {
      background: var(--azul-marino-claro);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(26, 54, 93, 0.3);
    }

    .btn-calificaciones {
      background: var(--verde-olivo);
      color: white;
    }

    .btn-calificaciones:hover {
      background: var(--verde-olivo-claro);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
    }

    .btn-asistencia {
      background: var(--gris-medio);
      color: var(--negro);
    }

    .btn-asistencia:hover {
      background: var(--azul-marino-claro);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(44, 82, 130, 0.3);
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }

    .empty-state svg {
      width: 80px;
      height: 80px;
      margin-bottom: 20px;
      opacity: 0.5;
      color: var(--azul-marino);
    }

    .empty-state h3 {
      color: var(--azul-marino);
      margin-bottom: 10px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
    }

    .badge-year {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
    }

    @media (max-width: 768px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .materias-grid {
        grid-template-columns: 1fr;
      }

      .card-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>
        Mis Materias Asignadas
      </h1>
      <a href="../panel_profesor.php" class="btn btn-volver">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Volver al Panel
      </a>
    </div>

    <div class="content">
      <?php if(count($materias) > 0): ?>
        <div class="search-container">
          <input type="text" id="searchInput" placeholder="Buscar por nombre de materia o carrera..." onkeyup="filterMaterias()">
          <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
        </div>

        <div class="materias-grid" id="materiasGrid">
          <?php foreach($materias as $row): ?>
          <div class="materia-card" data-nombre="<?php echo htmlspecialchars($row['nombre']); ?>" data-carrera="<?php echo htmlspecialchars($row['nombre_carrera']); ?>">
            <div class="card-header">
              <h3><?php echo htmlspecialchars($row['nombre']); ?></h3>
              <div class="card-info">
                <div class="info-item">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                  </svg>
                  <?php echo htmlspecialchars($row['nombre_carrera']); ?>
                </div>
                <span class="badge badge-year">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  Año <?php echo $row['año']; ?>
                </span>
              </div>
            </div>
            
            <div class="card-body">
              <div class="stat-row">
                <div class="stat-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                  </svg>
                </div>
                <div class="stat-info">
                  <div class="stat-label">Estudiantes Inscritos</div>
                  <div class="stat-value"><?php echo $row['total_estudiantes']; ?></div>
                </div>
              </div>

              <div class="card-actions">
                <a href="lista_estudiantes.php?materia=<?php echo $row['id']; ?>" class="btn-action btn-ver">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                  </svg>
                  Estudiantes
                </a>
                <a href="cargar_calificaciones.php?materia=<?php echo $row['id']; ?>" class="btn-action btn-calificaciones">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                  </svg>
                  Calificaciones
                </a>
                <a href="tomar_asistencia.php?materia=<?php echo $row['id']; ?>" class="btn-action btn-asistencia">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 11 12 14 22 4"></polyline>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                  </svg>
                  Asistencia
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
          <h3>No tienes materias asignadas</h3>
          <p>Actualmente no tienes ninguna materia asignada en el sistema.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function filterMaterias() {
      const input = document.getElementById('searchInput');
      const filter = input.value.toLowerCase();
      const cards = document.querySelectorAll('.materia-card');
      
      cards.forEach(card => {
        const nombre = card.getAttribute('data-nombre').toLowerCase();
        const carrera = card.getAttribute('data-carrera').toLowerCase();
        
        if (nombre.includes(filter) || carrera.includes(filter)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>