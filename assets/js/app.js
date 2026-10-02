"use strict";

const menuButton = document.querySelector(".menu-button");

if (menuButton) {
    menuButton.addEventListener("click", () => {
        const expanded =
            menuButton.getAttribute("aria-expanded") === "true";

        menuButton.setAttribute(
            "aria-expanded",
            String(!expanded)
        );
    });
}