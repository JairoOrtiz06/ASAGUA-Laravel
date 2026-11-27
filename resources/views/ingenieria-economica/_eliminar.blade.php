<form class="inline" method="POST" action="{{ $ruta }}"
    onsubmit="return confirm(this.dataset.confirmacion)"
    data-confirmacion="{{ '¿'.$accion.' «'.$nombre.'»? La operación es permanente. '.$conserva }}">
    @csrf
    @method('DELETE')
    <button class="button warning" type="submit" aria-label="{{ $accion }} {{ $nombre }}">{{ $accion }}</button>
</form>
