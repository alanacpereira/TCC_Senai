(() => {
    "use strict";

    document.querySelectorAll("[data-toggle-password]").forEach((button) => {
        const input = document.getElementById(button.getAttribute("aria-controls"));
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        button.addEventListener("click", () => {
            const mostrar = input.type === "password";
            input.type = mostrar ? "text" : "password";
            button.setAttribute("aria-pressed", String(mostrar));
            button.textContent = mostrar ? "Ocultar" : "Mostrar";
        });
    });
})();
