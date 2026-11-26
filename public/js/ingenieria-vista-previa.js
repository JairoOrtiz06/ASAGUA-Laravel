document.querySelectorAll('form[data-vista-previa]').forEach((formulario) => {
    const salida = formulario.querySelector('[data-resultado]');
    const boton = formulario.querySelector('[data-actualizar]');
    let turno = 0;
    let temporizador;
    let solicitud;

    function mostrar(lineas) {
        const lista = document.createElement('ul');
        for (const texto of lineas) {
            const elemento = document.createElement('li');
            elemento.textContent = texto;
            lista.append(elemento);
        }
        salida.replaceChildren(lista);
    }

    async function actualizar() {
        const actual = ++turno;
        solicitud?.abort();
        solicitud = new AbortController();
        const datos = new FormData(formulario);
        datos.delete('_method');
        salida.textContent = 'Calculando vista previa…';
        try {
            const respuesta = await fetch(formulario.dataset.vistaPrevia, {
                method: 'POST',
                body: datos,
                headers: { Accept: 'application/json' },
                signal: solicitud.signal,
            });
            if (actual !== turno) return;
            if (respuesta.status === 422) {
                const { errors } = await respuesta.json();
                if (actual === turno) mostrar(Object.values(errors).flat());
                return;
            }
            if (!respuesta.ok) throw new Error('Vista previa no disponible');
            const resultado = await respuesta.json();
            if (actual !== turno) return;
            if (resultado.resumen) {
                mostrar([resultado.resumen].flat());
                return;
            }
            const lineas = [
                `${resultado.escenario} · ${resultado.base} a ${resultado.cierre}.`,
                `Primera fecha programada: ${resultado.primero ?? 'sin movimientos'} · Última fecha programada: ${resultado.ultimo ?? 'sin movimientos'}.`,
                `${resultado.movimientos} de ${resultado.programados} movimientos programados incluidos · Total nominal: ${resultado.total} ${resultado.moneda}.`,
                `${resultado.fuera} movimientos excluidos por el horizonte según los límites ingresados.`,
                ...resultado.motivos,
            ];
            mostrar(lineas);
            if (resultado.importes.length) {
                const unicos = new Set(resultado.importes);
                const detalle = document.createElement('details');
                const titulo = document.createElement('summary');
                titulo.textContent = unicos.size === 1
                    ? `Importe por movimiento: ${resultado.importes[0]} ${resultado.moneda}`
                    : 'Importe por movimiento incluido';
                detalle.append(titulo);
                if (unicos.size > 1) {
                    const lista = document.createElement('ol');
                    for (const importe of resultado.importes) {
                        const elemento = document.createElement('li');
                        elemento.textContent = `${importe} ${resultado.moneda}`;
                        lista.append(elemento);
                    }
                    detalle.append(lista);
                } else {
                    const nota = document.createElement('p');
                    nota.textContent = 'Importe constante en el calendario mostrado.';
                    detalle.append(nota);
                }
                salida.append(detalle);
            }
        } catch (error) {
            if (actual === turno && error.name !== 'AbortError') {
                salida.textContent = 'No se pudo calcular la vista previa. Revisa la conexión y pulsa Actualizar vista previa.';
            }
        }
    }

    formulario.addEventListener('input', (evento) => {
        if (evento.target.closest('[data-resultado]')) return;
        clearTimeout(temporizador);
        turno++;
        solicitud?.abort();
        temporizador = setTimeout(actualizar, 450);
    });
    boton.addEventListener('click', () => {
        clearTimeout(temporizador);
        actualizar();
    });
    if (formulario.dataset.vistaInicial === '1') actualizar();
});
