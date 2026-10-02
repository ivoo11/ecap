"use strict";

const menuButton = document.querySelector(".menu-button");
const mobileNav = document.querySelector(".mobile-nav");

if (menuButton && mobileNav) {

    const closeMenu = () => {
        menuButton.setAttribute("aria-expanded", "false");
        menuButton.setAttribute("aria-label", "Abrir menú");
        mobileNav.classList.remove("is-open");
    };

    menuButton.addEventListener("click", () => {

        const isOpen =
            menuButton.getAttribute("aria-expanded") === "true";

        menuButton.setAttribute(
            "aria-expanded",
            String(!isOpen)
        );

        menuButton.setAttribute(
            "aria-label",
            isOpen ? "Abrir menú" : "Cerrar menú"
        );

        mobileNav.classList.toggle("is-open", !isOpen);
    });


    mobileNav.querySelectorAll("a").forEach((link) => {

        link.addEventListener("click", () => {
            closeMenu();
        });

    });


    window.addEventListener("resize", () => {

        if (window.innerWidth >= 768) {
            closeMenu();
        }

    });
}