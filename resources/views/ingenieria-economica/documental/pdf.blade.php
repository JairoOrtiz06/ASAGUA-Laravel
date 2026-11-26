<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe económico ASAGUA</title>
    <style>
        @page { margin: 48pt 40pt 45pt; }
        body { font: 9pt "DejaVu Sans", sans-serif; color: #17324d; line-height: 1.35; }
        h1,h2,h3,h4 { color: #0a4d8c; }
        h1 { font-size: 24pt; }
        h2 { font-size: 15pt; }
        h3 { font-size: 12pt; margin-top: 15pt; }
        header { position: fixed; top: -30pt; left: 0; right: 0; border-bottom: 1pt solid #b9d6e9; color: #0a4d8c; font-size: 8pt; }
        footer { position: fixed; bottom: -27pt; left: 0; right: 0; border-top: 1pt solid #b9d6e9; font-size: 8pt; }
        .portada { padding-top: 85pt; page-break-after: always; }
        .estado { color: #0a4d8c; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; margin: 7pt 0 12pt; }
        thead { display: table-header-group; }
        th,td { border: 0.5pt solid #b9d6e9; padding: 4pt; vertical-align: top; word-wrap: break-word; }
        thead th { background: #eaf4fc; color: #0a4d8c; }
        p,li { word-wrap: break-word; }
        .description { white-space: pre-wrap; word-wrap: break-word; }
        .errors { background: #fff3e4; border: 0.5pt solid #c8752b; padding: 6pt; }
    </style>
</head>
<body>
<header>ASAGUA · Ingeniería Económica · Universidad de El Salvador</header>
<footer>Expediente económico · {{ $informe['folio'] }}</footer>
<section class="portada">
    <p>UNIVERSIDAD DE EL SALVADOR</p>
    <p>Asignatura de Ingeniería Económica · Proyecto integrador ASAGUA</p>
    <h1>{{ $informe['evaluacion']['proyecto']['nombre'] }}</h1>
    <h2>Informe de evaluación económica</h2>
    <p class="estado">{{ mb_strtoupper($informe['estado']) }} · Evaluación #{{ $informe['evaluacion_id'] }}</p>
    <p>Emisión {{ \Carbon\CarbonImmutable::parse($informe['fecha_emision'])->format('d/m/Y H:i T') }}</p>
    <p>Responsable {{ $informe['responsable']['nombre'] }} · Contador #{{ $informe['responsable']['id'] }}</p>
    @if ($informe['estudiantes'])<p class="description">Estudiantes · {{ $informe['estudiantes'] }}</p>@endif
    @if ($informe['docentes'])<p class="description">Docentes · {{ $informe['docentes'] }}</p>@endif
    <p>Folio {{ $informe['folio'] }}</p>
</section>
@include('ingenieria-economica.documental._contenido',['pdf'=>true])
</body>
</html>
