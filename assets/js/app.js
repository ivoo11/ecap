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

/* =========================================================
   HOME HERO · CAROUSEL
   ========================================================= */

const heroSlides =
    Array.from(document.querySelectorAll(".home-hero-slide"));

const heroDots =
    Array.from(document.querySelectorAll(".home-hero-dot"));

const heroPrev =
    document.querySelector(".home-hero-arrow-prev");

const heroNext =
    document.querySelector(".home-hero-arrow-next");

if (heroSlides.length > 1 && heroDots.length === heroSlides.length) {

    let currentSlide = 0;
    let autoplayTimer = null;
    let touchStartX = 0;

    const AUTOPLAY_DELAY = 7500;


    const showSlide = (index) => {

        currentSlide =
            (index + heroSlides.length) % heroSlides.length;

        heroSlides.forEach((slide, slideIndex) => {

            const isActive = slideIndex === currentSlide;

            slide.classList.toggle("is-active", isActive);

        });


        heroDots.forEach((dot, dotIndex) => {

            const isActive = dotIndex === currentSlide;

            dot.classList.toggle("is-active", isActive);

            dot.setAttribute(
                "aria-current",
                isActive ? "true" : "false"
            );

        });

    };


    const stopAutoplay = () => {

        if (autoplayTimer) {
            window.clearInterval(autoplayTimer);
            autoplayTimer = null;
        }

    };


    const startAutoplay = () => {

        stopAutoplay();

        autoplayTimer = window.setInterval(() => {

            showSlide(currentSlide + 1);

        }, AUTOPLAY_DELAY);

    };


    heroDots.forEach((dot, index) => {

        dot.addEventListener("click", () => {

            showSlide(index);
            startAutoplay();

        });

    });

    if (heroPrev) {

    heroPrev.addEventListener("click", () => {

        showSlide(currentSlide - 1);
        startAutoplay();

    });

    }


    if (heroNext) {

        heroNext.addEventListener("click", () => {

            showSlide(currentSlide + 1);
            startAutoplay();

        });

    }

    const hero =
        document.querySelector(".home-hero");


    if (hero) {

        hero.addEventListener(
            "touchstart",
            (event) => {

                touchStartX =
                    event.changedTouches[0].clientX;

            },
            { passive: true }
        );


        hero.addEventListener(
            "touchend",
            (event) => {

                const touchEndX =
                    event.changedTouches[0].clientX;

                const distance =
                    touchEndX - touchStartX;


                if (Math.abs(distance) < 50) {
                    return;
                }


                if (distance < 0) {
                    showSlide(currentSlide + 1);
                } else {
                    showSlide(currentSlide - 1);
                }


                startAutoplay();

            },
            { passive: true }
        );

    }


    showSlide(0);
    startAutoplay();

}