document.addEventListener("DOMContentLoaded", function () {
  const links = document.querySelectorAll("#sidebar .nav-link");
  const contenido = document.getElementById("contenido");
  const menuActual = document.getElementById("menu-actual");

  links.forEach(link => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      const section = this.getAttribute("data-section");

      // Cambiar título en la barra superior
      menuActual.textContent = this.textContent.trim();

      // Cargar contenido dinámico
      fetch(section + ".html")
        .then(res => res.text())
        .then(data => {
          contenido.innerHTML = data;
        })
        .catch(() => {
          contenido.innerHTML = "<p>Error al cargar el contenido.</p>";
        });
    });
  });
});
