document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll(".nav-link[data-seccion]");
  const contenedor = document.getElementById("contenido-principal");
  const titulo = document.getElementById("titulo-seccion");

  function cargarSeccion(seccion, push = true) {
    // Actualizar estado activo del menú
    links.forEach(l => l.classList.toggle("activo", l.getAttribute("data-seccion") === seccion));

    // Actualizar título
    const linkActivo = Array.from(links).find(l => l.getAttribute("data-seccion") === seccion);
    if (linkActivo && titulo) titulo.textContent = linkActivo.textContent.replace(/^[^\wáéíóúñ]*\s*/, "");

    // Cargar via fetch el archivo de la carpeta secciones
    fetch(`secciones/${seccion}.php`, { cache: "no-cache", credentials: "same-origin" })
      .then(r => {
        if (!r.ok) throw new Error(`No se pudo cargar la sección "${seccion}".`);
        return r.text();
      })
      .then(html => {
        contenedor.innerHTML = html;
        if (push) {
          // Usamos hash para navegación simple
          history.pushState({ seccion }, "", `#${seccion}`);
        }
        // Foco al contenedor para accesibilidad
        contenedor.setAttribute("tabindex", "-1");
        contenedor.focus({ preventScroll: true });
      })
      .catch(err => {
        contenedor.innerHTML = `<div class="alert alert-danger mb-0">${err.message}</div>`;
      });
  }

  // Click en menú
  links.forEach(link => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const seccion = link.getAttribute("data-seccion");
      cargarSeccion(seccion, true);
    });
  });

  // Carga inicial según hash (#materias, etc.)
  const hash = (location.hash || "#inicio").replace("#", "");
  cargarSeccion(hash, false);

  // Volver/adelante del navegador
  window.addEventListener("popstate", (e) => {
    const seccion = (location.hash || "#inicio").replace("#", "");
    cargarSeccion(seccion, false);
  });
});
