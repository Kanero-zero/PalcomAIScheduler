/* SCHEDORA AI Sidebar V2: optional desktop collapse. Flux handles mobile drawer. */
(() => {
    function initSchedoraSidebar() {
        const sidebar = document.querySelector('.schedora-sidebar');
        const toggle = document.querySelector('[data-schedora-collapse]');
        if (!sidebar || !toggle || toggle.dataset.schedoraBound === 'true') return;
        toggle.dataset.schedoraBound = 'true';

        const setCollapsed = (collapsed) => {
            document.body.classList.toggle('schedora-collapsed', collapsed);
            toggle.setAttribute('aria-pressed', String(collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'Perluas sidebar' : 'Ringkas sidebar');
            toggle.title = collapsed ? 'Perluas sidebar' : 'Ringkas sidebar';
            for (const item of sidebar.querySelectorAll('.schedora-nav-item[data-label]')) {
                if (collapsed) item.title = item.dataset.label + (item.disabled ? ' — Untuk pengembangan selanjutnya' : '');
                else if (!item.disabled) item.removeAttribute('title');
                else item.title = 'Untuk pengembangan selanjutnya';
            }
        };

        toggle.addEventListener('click', () => setCollapsed(!document.body.classList.contains('schedora-collapsed')));
        setCollapsed(document.body.classList.contains('schedora-collapsed'));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSchedoraSidebar);
    else initSchedoraSidebar();
    document.addEventListener('livewire:navigated', initSchedoraSidebar);
})();
