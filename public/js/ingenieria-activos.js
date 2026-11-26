document.querySelectorAll('[data-filas]').forEach((editor) => {
    const lista = editor.querySelector('[data-lista-filas]');
    const plantilla = editor.querySelector('template');
    const agregar = editor.querySelector('[data-agregar]');
    let siguiente = Math.max(-1, ...Array.from(lista.querySelectorAll('[data-fila]'), (fila) =>
        Number(fila.querySelector('input').name.match(/\[(\d+)\]\[anio\]$/)?.[1] ?? -1))) + 1;

    agregar.addEventListener('click', () => {
        if (lista.children.length >= 100) return;
        const fragmento = plantilla.content.cloneNode(true);
        for (const elemento of fragmento.querySelectorAll('[id], [name], [for]')) {
            for (const atributo of ['id', 'name', 'for']) {
                if (elemento.hasAttribute(atributo)) {
                    elemento.setAttribute(atributo, elemento.getAttribute(atributo).replaceAll('__fila__', siguiente));
                }
            }
        }
        siguiente++;
        const primero = fragmento.querySelector('input');
        lista.append(fragmento);
        primero.focus();
    });

    lista.addEventListener('click', (evento) => {
        const quitar = evento.target.closest('[data-quitar]');
        if (!quitar) return;
        quitar.closest('[data-fila]').remove();
        agregar.focus();
    });
});

const clasificacion = document.getElementById('clasificacion_contable');
if (clasificacion) {
    const actualizar = () => {
        for (const grupo of document.querySelectorAll('[data-tipo-activo]')) {
            grupo.hidden = !grupo.dataset.tipoActivo.split(' ').includes(clasificacion.value);
        }
    };
    clasificacion.addEventListener('change', actualizar);
    actualizar();
}
