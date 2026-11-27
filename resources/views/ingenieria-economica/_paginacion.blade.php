@if ($paginas->hasPages())
    <nav class="pagination actions" aria-label="{{ $etiquetaPaginacion ?? 'Paginación' }}">
        @if ($paginas->previousPageUrl())
            <a href="{{ $paginas->previousPageUrl() }}">Anterior</a>
        @endif
        <span>{{ isset($etiquetaPaginacion) ? $etiquetaPaginacion.' · ' : '' }}Página {{ $paginas->currentPage() }} de {{ $paginas->lastPage() }}</span>
        @if ($paginas->nextPageUrl())
            <a href="{{ $paginas->nextPageUrl() }}">Siguiente</a>
        @endif
    </nav>
@endif
