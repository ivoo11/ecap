(() => {
    'use strict';

    const body = document.body;
    const toggle = document.querySelector('.admin-menu-toggle');
    const sidebar = document.querySelector('#admin-sidebar');
    const overlay = document.querySelector('[data-sidebar-close]');

    if (!toggle || !sidebar) {
        return;
    }

    const openSidebar = () => {
        body.classList.add('admin-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
    };

    const closeSidebar = () => {
        body.classList.remove('admin-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        if (body.classList.contains('admin-sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 960) {
            closeSidebar();
        }
    });
})();