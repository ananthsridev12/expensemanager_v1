document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const toggle = document.getElementById('menu-toggle');
    const closeBtn = document.getElementById('nav-close');
    const backdrop = document.getElementById('sidebar-backdrop');

    if (!body || !toggle || !closeBtn || !backdrop) {
        return;
    }

    function closeMenu() {
        body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        backdrop.hidden = true;
    }

    function openMenu() {
        body.classList.add('nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        backdrop.hidden = false;
    }

    toggle.addEventListener('click', function () {
        if (body.classList.contains('nav-open')) {
            closeMenu();
            return;
        }
        openMenu();
    });

    closeBtn.addEventListener('click', closeMenu);
    backdrop.addEventListener('click', closeMenu);

    window.addEventListener('resize', function () {
        if (window.innerWidth > 980) {
            closeMenu();
        }
    });
});
