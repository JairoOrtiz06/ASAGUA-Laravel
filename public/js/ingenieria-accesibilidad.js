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

    const filtroProyectos = document.getElementById('filtro-proyectos');
    if (filtroProyectos) {
        const campo = document.getElementById('buscar-proyecto');
        const estado = document.getElementById('estado-busqueda-proyectos');
        const resultados = document.getElementById('resultados-proyectos');
        let espera;
        let solicitud;

        const buscar = async (url, consulta) => {
            const peticion = new AbortController();
            solicitud = peticion;

            try {
                const respuesta = await fetch(url, { signal: peticion.signal });
                if (!respuesta.ok) throw new Error('Error al cargar proyectos');
                const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                const nuevosResultados = pagina.getElementById('resultados-proyectos');
                if (peticion.signal.aborted) return;
                if (!nuevosResultados) throw new Error('Resultados no disponibles');

                window.history.replaceState(window.history.state, '', url);
                resultados.replaceChildren(...nuevosResultados.children);
                actualizarTablas();

                const total = Number(nuevosResultados.dataset.total);
                estado.textContent = total === 1 ? '1 proyecto encontrado.' : `${total} proyectos encontrados.`;
                estado.hidden = consulta === '';
            } catch {
                if (!peticion.signal.aborted) {
                    estado.textContent = 'No se pudo actualizar la búsqueda. Pulsa Enter para reintentar.';
                }
            }
        };

        const programarBusqueda = () => {
            window.clearTimeout(espera);
            solicitud?.abort();

            const consulta = campo.value.trim();
            const url = new URL(filtroProyectos.action);
            if (consulta !== '') url.searchParams.set('buscar', consulta);
            estado.textContent = 'Buscando proyectos…';
            estado.hidden = false;
            espera = window.setTimeout(() => buscar(url, consulta), 250);
        };

        campo.addEventListener('input', (evento) => {
            if (!evento.isComposing) programarBusqueda();
        });
        campo.addEventListener('compositionend', programarBusqueda);
    }

    economia.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[href^="#"]');
        const destino = enlace && document.getElementById(enlace.hash.slice(1));
        if (destino?.tagName === 'DETAILS') {
            destino.open = true;
            destino.querySelector('summary')?.focus();
        }
    });
}
