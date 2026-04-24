document.addEventListener('DOMContentLoaded', function () {
    // Font size toggle
    const fontBtns = document.querySelectorAll('.font-size-btns [data-font]');
    const current = document.documentElement.getAttribute('data-font') || 'normal';
    fontBtns.forEach(function (btn) {
        if (btn.dataset.font === current) btn.classList.add('is-active');
        btn.addEventListener('click', function () {
            const size = this.dataset.font;
            if (size === 'normal') {
                document.documentElement.removeAttribute('data-font');
                localStorage.removeItem('em-font');
            } else {
                document.documentElement.setAttribute('data-font', size);
                localStorage.setItem('em-font', size);
            }
            fontBtns.forEach(function (b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');
        });
    });

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
