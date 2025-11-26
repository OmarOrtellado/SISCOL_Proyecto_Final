document.addEventListener("DOMContentLoaded", function () {
  const enlaces = document.querySelectorAll(".nav-link");
  const contenido = document.getElementById("contenido");

  enlaces.forEach(enlace => {
    enlace.addEventListener("click", function (e) {
      e.preventDefault();
      const seccion = this.getAttribute("data-seccion");

      fetch("secciones/" + seccion)
        .then(respuesta => respuesta.text())
        .then(html => {
          contenido.innerHTML = html;
        })
        .catch(error => {
          contenido.innerHTML = "<p class='text-danger'>Error al cargar la sección.</p>";
          console.error("Error:", error);
        });
    });
  });
});
