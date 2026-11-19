<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentoFuenteRequest;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Services\DocumentoFuenteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DocumentoFuenteController extends Controller
{
    public function index(): View
    {
        $documentos = DocumentoFuente::query()
            ->withCount('detalles')
            ->orderByDesc('id_documento')
            ->paginate(20);

        return view('documentos.index', compact('documentos'));
    }

    public function create(): View
    {
        $detalles = DetallePartida::query()
            ->whereNull('id_documento')
            ->with(['partida', 'cuenta'])
            ->orderByDesc('id_detalle')
            ->get();

        return view('documentos.create', compact('detalles'));
    }

    public function store(DocumentoFuenteRequest $request, DocumentoFuenteService $service): RedirectResponse
    {
        abort_unless($request->user()?->rol === 'Contador', 403);

        $documento = $service->registrar(
            $request->validated(),
            $request->file('archivo'),
        );

        return redirect()->route('documentos.show', $documento)
            ->with('status', 'Documento fuente registrado correctamente.');
    }

    public function show(DocumentoFuente $documento): View
    {
        $documento->load('detalles.partida', 'detalles.cuenta');

        return view('documentos.show', compact('documento'));
    }

    public function download(DocumentoFuente $documento): Response
    {
        abort_if($documento->archivo === null, 404, 'El documento no contiene archivo.');

        $mime = strtoupper($documento->tipo_documento) === 'PDF'
            ? 'application/pdf'
            : 'application/octet-stream';
        $nombre = str_replace(['"', "\r", "\n"], '', $documento->nombre_documento);

        return response($documento->archivo)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="'.$nombre.'"');
    }
}
