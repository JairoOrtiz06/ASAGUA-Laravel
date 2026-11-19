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
use App\Http\Controllers\ReportePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('auth.login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'redirectByRole'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
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
