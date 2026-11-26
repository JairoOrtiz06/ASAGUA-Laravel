const economia = document.getElementById('contenido-economico');
if (economia) {
    const actualizarTablas = () => {
        economia.querySelectorAll('.table-wrap').forEach((region) => {
            if (!region.querySelector('table')) return;
            if (region.scrollWidth > region.clientWidth && region.clientWidth > 0) {
                if (!region.hasAttribute('tabindex')) {
                    region.tabIndex = 0;
                    region.dataset.focoTabla = 'true';
                }
                if (!region.hasAttribute('aria-label')) {
                    region.setAttribute('role', 'region');
                    region.setAttribute('aria-label', region.querySelector('caption')?.textContent.trim() || 'Tabla desplazable horizontalmente');
                }
            } else if (region.dataset.focoTabla) {
                region.removeAttribute('tabindex');
                delete region.dataset.focoTabla;
            }
        });
    };
    actualizarTablas();
    window.addEventListener('resize', actualizarTablas);
    economia.addEventListener('toggle', actualizarTablas, true);
    economia.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[href^="#"]');
        const destino = enlace && document.getElementById(enlace.hash.slice(1));
        if (destino?.tagName === 'DETAILS') {
            destino.open = true;
            destino.querySelector('summary')?.focus();
        }
    });
}
