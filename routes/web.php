<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AjusteController;
use App\Http\Controllers\CuentaContableController;
use App\Http\Controllers\BalanceComprobacionController;
use App\Http\Controllers\BalanceGeneralController;
use App\Http\Controllers\EstadoResultadosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoFuenteController;
use App\Http\Controllers\PartidaController;
use App\Http\Controllers\LibroContableController;
use App\Http\Controllers\HojaTrabajoController;
use App\Http\Controllers\IngenieriaEconomica\ActivoController;
use App\Http\Controllers\IngenieriaEconomica\AjusteFlujoController;
use App\Http\Controllers\IngenieriaEconomica\CalculoActivoController;
use App\Http\Controllers\IngenieriaEconomica\IntegracionActivoController;
use App\Http\Controllers\IngenieriaEconomica\ConceptoController;
use App\Http\Controllers\IngenieriaEconomica\FlujoController;
use App\Http\Controllers\IngenieriaEconomica\FuenteConceptoController;
use App\Http\Controllers\IngenieriaEconomica\AlternativaController;
use App\Http\Controllers\IngenieriaEconomica\EscenarioController;
use App\Http\Controllers\IngenieriaEconomica\EvaluacionController;
use App\Http\Controllers\IngenieriaEconomica\EvidenciaDocumentalController;
use App\Http\Controllers\IngenieriaEconomica\InformeEconomicoController;
use App\Http\Controllers\IngenieriaEconomica\SensibilidadController;
use App\Http\Controllers\IngenieriaEconomica\ProyectoController;
use App\Http\Controllers\IngenieriaEconomica\HerramientaFinancieraController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('auth.login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', \App\Http\Middleware\EnsureUserIsActive::class])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'redirectByRole'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('role:Contador')->group(function (): void {
        Route::get('/portal/contador', [PortalController::class, 'contador'])->name('portal.contador');
        Route::prefix('ingenieria-economica')->name('ingenieria-economica.')->scopeBindings()
            ->middleware(\App\Http\Middleware\ResolverContextoEvaluacion::class)
            ->where(['proyecto' => '[0-9]+', 'alternativa' => '[0-9]+', 'escenario' => '[0-9]+', 'concepto' => '[0-9]+', 'fuente' => '[0-9]+', 'activo' => '[0-9]+'])
            ->group(function (): void {
                Route::view('/', 'ingenieria-economica.index')->name('index');
                Route::prefix('herramientas')->name('herramientas.')->group(function (): void {
                    $herramientas = array_keys(HerramientaFinancieraController::HERRAMIENTAS);
                    Route::get('/', [HerramientaFinancieraController::class, 'index'])->name('index');
                    Route::get('/{herramienta}', [HerramientaFinancieraController::class, 'show'])
                        ->whereIn('herramienta', $herramientas)->name('show');
                    Route::post('/{herramienta}', [HerramientaFinancieraController::class, 'calcular'])
                        ->whereIn('herramienta', $herramientas)->name('calcular');
                });
                Route::resource('proyectos', ProyectoController::class)
                    ->parameters(['proyectos' => 'proyecto']);
                Route::resource('proyectos.alternativas', AlternativaController::class)->except('show')
                    ->parameters(['proyectos' => 'proyecto', 'alternativas' => 'alternativa']);
                Route::post('proyectos/{proyecto}/conceptos/previsualizar', [ConceptoController::class, 'previsualizar'])
                    ->name('proyectos.conceptos.previsualizar');
                Route::post('proyectos/{proyecto}/conceptos/{concepto}/previsualizar', [ConceptoController::class, 'previsualizarEdicion'])
                    ->name('proyectos.conceptos.previsualizarEdicion');
                Route::resource('proyectos.conceptos', ConceptoController::class)
                    ->parameters(['proyectos' => 'proyecto', 'conceptos' => 'concepto']);
                Route::resource('proyectos.conceptos.fuentes', FuenteConceptoController::class)
                    ->only(['create', 'store', 'edit', 'update', 'destroy'])
                    ->parameters(['proyectos' => 'proyecto', 'conceptos' => 'concepto', 'fuentes' => 'fuente']);
                Route::resource('proyectos.activos', ActivoController::class)
                    ->parameters(['proyectos' => 'proyecto', 'activos' => 'activo']);
                Route::prefix('proyectos/{proyecto}/activos/{activo}')->name('proyectos.activos.')->group(function (): void {
                    Route::get('/depreciacion', [CalculoActivoController::class, 'depreciacion'])->name('depreciacion');
                    Route::post('/depreciacion', [CalculoActivoController::class, 'calcularDepreciacion'])->name('depreciacion.store');
                    Route::get('/reemplazo', [CalculoActivoController::class, 'reemplazo'])->name('reemplazo');
                    Route::post('/reemplazo', [CalculoActivoController::class, 'calcularReemplazo'])->name('reemplazo.store');
                    Route::get('/calculos/{calculo}', [CalculoActivoController::class, 'show'])->whereNumber('calculo')->name('calculos.show');
                    Route::get('/calculos/{calculo}/csv', [CalculoActivoController::class, 'csv'])->whereNumber('calculo')->name('calculos.csv');
                    Route::get('/fuentes/create', [IntegracionActivoController::class, 'fuente'])->name('fuentes.create');
                    Route::delete('/fuentes/{fuente}', [IntegracionActivoController::class, 'retirarFuente'])->name('fuentes.destroy');
                    Route::post('/fuentes', [IntegracionActivoController::class, 'guardarFuente'])->name('fuentes.store');
                    Route::get('/flujo/create', [IntegracionActivoController::class, 'flujo'])->name('flujo.create');
                    Route::post('/flujo', [IntegracionActivoController::class, 'guardarFlujo'])->name('flujo.store');
                });
                Route::get('proyectos/{proyecto}/flujos', [FlujoController::class, 'index'])->name('proyectos.flujos.index');
                Route::prefix('proyectos/{proyecto}/sensibilidad')->name('proyectos.sensibilidad.')->group(function (): void {
                    Route::get('/', [SensibilidadController::class, 'index'])->name('index');
                    Route::get('/nuevo', [SensibilidadController::class, 'create'])->name('create');
                    Route::post('/vista-previa', [SensibilidadController::class, 'preview'])->name('preview');
                    Route::post('/', [SensibilidadController::class, 'store'])->name('store');
                    Route::get('/{analisis}', [SensibilidadController::class, 'show'])->whereNumber('analisis')->name('show');
                });
                Route::prefix('proyectos/{proyecto}')->name('proyectos.')->middleware('cache.headers:private;no_store')->group(function (): void {
                    Route::get('/validacion', [InformeEconomicoController::class, 'calidad'])->name('validacion');
                    Route::get('/referencias-contables', [InformeEconomicoController::class, 'referencias'])->name('referencias');
                    Route::prefix('evidencias')->name('evidencias.')->group(function (): void {
                        Route::get('/', [EvidenciaDocumentalController::class, 'index'])->name('index');
                        Route::get('/nueva', [EvidenciaDocumentalController::class, 'create'])->name('create');
                        Route::post('/', [EvidenciaDocumentalController::class, 'store'])->name('store');
                        Route::get('/{evidencia}', [EvidenciaDocumentalController::class, 'show'])->whereNumber('evidencia')->name('show');
                        Route::get('/{evidencia}/archivo', [EvidenciaDocumentalController::class, 'download'])->whereNumber('evidencia')->name('download');
                        Route::post('/{evidencia}/adjunto', [EvidenciaDocumentalController::class, 'adjuntar'])->whereNumber('evidencia')->name('adjuntar');
                        Route::post('/{evidencia}/estado', [EvidenciaDocumentalController::class, 'estado'])->whereNumber('evidencia')->name('estado');
                        Route::delete('/{evidencia}', [EvidenciaDocumentalController::class, 'destroy'])->whereNumber('evidencia')->name('destroy');
                        Route::delete('/{evidencia}/vinculos/{vinculo}', [EvidenciaDocumentalController::class, 'desvincular'])
                            ->whereNumber(['evidencia', 'vinculo'])->name('desvincular');
                        Route::post('/{evidencia}/vinculos', [EvidenciaDocumentalController::class, 'vincular'])->whereNumber('evidencia')->name('vincular');
                    });
                    Route::prefix('informes')->name('informes.')->group(function (): void {
                        Route::get('/', [InformeEconomicoController::class, 'index'])->name('index');
                        Route::post('/vista-previa', [InformeEconomicoController::class, 'preview'])->name('preview');
                        Route::post('/', [InformeEconomicoController::class, 'store'])->name('store');
                        Route::get('/{informe}', [InformeEconomicoController::class, 'show'])->whereNumber('informe')->name('show');
                        Route::get('/{informe}/pdf', [InformeEconomicoController::class, 'download'])->whereNumber('informe')->name('download');
                    });
                });
                Route::prefix('proyectos/{proyecto}/evaluaciones')->name('proyectos.evaluaciones.')->group(function (): void {
                    Route::get('/', [EvaluacionController::class, 'index'])->name('index');
                    Route::post('/seleccion', [EvaluacionController::class, 'seleccion'])->name('seleccion');
                    Route::post('/vista-previa', [EvaluacionController::class, 'preview'])->name('preview');
                    Route::post('/', [EvaluacionController::class, 'store'])->name('store');
                    Route::get('/{evaluacion}', [EvaluacionController::class, 'show'])->whereNumber('evaluacion')->name('show');
                });
                Route::get('proyectos/{proyecto}/ajustes-flujo/create', [AjusteFlujoController::class, 'create'])->name('proyectos.ajustes-flujo.create');
                Route::post('proyectos/{proyecto}/ajustes-flujo', [AjusteFlujoController::class, 'store'])->name('proyectos.ajustes-flujo.store');
                Route::delete('proyectos/{proyecto}/ajustes-flujo/{ajuste}', [AjusteFlujoController::class, 'destroy'])
                    ->whereNumber('ajuste')->name('proyectos.ajustes-flujo.destroy');
                Route::post('proyectos/{proyecto}/escenarios/previsualizar', [EscenarioController::class, 'previsualizar'])
                    ->name('proyectos.escenarios.previsualizar');
                Route::resource('proyectos.escenarios', EscenarioController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
                    ->parameters(['proyectos' => 'proyecto', 'escenarios' => 'escenario']);
                Route::patch('proyectos/{proyecto}/escenarios/{escenario}/principal', [EscenarioController::class, 'principal'])
                    ->name('proyectos.escenarios.principal');
            });
    });

    Route::get('/catalogo-cuentas', [CuentaContableController::class, 'index'])->name('catalogo.index');

    Route::middleware('role:Contador,Auditor')->group(function (): void {
        Route::get('/documentos', [DocumentoFuenteController::class, 'index'])->name('documentos.index');
        Route::get('/documentos/{documento}', [DocumentoFuenteController::class, 'show'])
            ->whereNumber('documento')->name('documentos.show');
        Route::get('/documentos/{documento}/archivo', [DocumentoFuenteController::class, 'download'])
            ->whereNumber('documento')->name('documentos.download');
    });

    Route::middleware('role:Contador,Auditor')->group(function (): void {
        Route::get('/partidas', [PartidaController::class, 'index'])->name('partidas.index');
        Route::get('/libros/diario', [LibroContableController::class, 'diario'])->name('libros.diario');
        Route::get('/libros/diario/pdf', [ReportePdfController::class, 'libroDiario'])->name('reportes.libro-diario.ver');
        Route::get('/libros/diario/pdf/descargar', [ReportePdfController::class, 'descargarLibroDiario'])->name('reportes.libro-diario.descargar');
        Route::get('/libros/mayor', [LibroContableController::class, 'mayor'])->name('libros.mayor');
        Route::get('/libros/mayor/pdf', [ReportePdfController::class, 'libroMayor'])->name('reportes.libro-mayor.ver');
        Route::get('/libros/mayor/pdf/descargar', [ReportePdfController::class, 'descargarLibroMayor'])->name('reportes.libro-mayor.descargar');
        Route::get('/balances/comprobacion', [BalanceComprobacionController::class, 'index'])->name('balances.comprobacion');
        Route::get('/balances/comprobacion/pdf', [ReportePdfController::class, 'balanceComprobacion'])->name('reportes.balance-comprobacion.ver');
        Route::get('/balances/comprobacion/pdf/descargar', [ReportePdfController::class, 'descargarBalanceComprobacion'])->name('reportes.balance-comprobacion.descargar');
        Route::get('/balances/general', [BalanceGeneralController::class, 'index'])->name('balances.general');
        Route::get('/balances/general/pdf', [ReportePdfController::class, 'balanceGeneral'])->name('reportes.balance-general.ver');
        Route::get('/balances/general/pdf/descargar', [ReportePdfController::class, 'descargarBalanceGeneral'])->name('reportes.balance-general.descargar');
        Route::get('/balances/resultados', [EstadoResultadosController::class, 'index'])->name('balances.resultados');
        Route::get('/balances/resultados/pdf', [ReportePdfController::class, 'estadoResultados'])->name('reportes.estado-resultados.ver');
        Route::get('/balances/resultados/pdf/descargar', [ReportePdfController::class, 'descargarEstadoResultados'])->name('reportes.estado-resultados.descargar');
    });

    Route::middleware('role:Contador')->group(function (): void {
        Route::get('/hoja-trabajo', [HojaTrabajoController::class, 'index'])->name('hoja-trabajo.index');
        Route::get('/partidas/registrar', [PartidaController::class, 'create'])->name('partidas.create');
        Route::post('/partidas', [PartidaController::class, 'store'])->name('partidas.store');
        Route::get('/ajustes', [AjusteController::class, 'index'])->name('ajustes.index');
        Route::get('/ajustes/registrar', [AjusteController::class, 'create'])->name('ajustes.create');
        Route::post('/ajustes', [AjusteController::class, 'store'])->name('ajustes.store');
        Route::get('/ajustes/{partida}', [AjusteController::class, 'show'])
            ->whereNumber('partida')->name('ajustes.show');
        Route::get('/documentos/registrar', [DocumentoFuenteController::class, 'create'])->name('documentos.create');
        Route::post('/documentos', [DocumentoFuenteController::class, 'store'])->name('documentos.store');
    });

    Route::middleware('role:Contador,Auditor')
        ->get('/partidas/{partida}', [PartidaController::class, 'show'])
        ->whereNumber('partida')
        ->name('partidas.show');

    Route::get('/panel/admin', [DashboardController::class, 'panel'])
        ->defaults('role', 'Admin')->middleware('role:Admin')->name('panel.admin');
    Route::get('/panel/contador', [DashboardController::class, 'panel'])
        ->defaults('role', 'Contador')->middleware('role:Contador')->name('panel.contador');
    Route::get('/panel/auditor', [DashboardController::class, 'panel'])
        ->defaults('role', 'Auditor')->middleware('role:Auditor')->name('panel.auditor');

    Route::middleware('role:Admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::resource('cuentas', CuentaContableController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy']);
    });
});
