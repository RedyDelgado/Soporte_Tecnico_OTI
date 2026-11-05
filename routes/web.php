<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DerivacionController;
use App\Http\Controllers\DirectorioController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PedidoPublicoController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Públicas
Route::get('/t/{token}', [TicketController::class, 'seguimiento'])->name('seguimiento');
Route::get('/pedido', [PedidoPublicoController::class, 'create'])->name('pedido.publico');
Route::post('/pedido', [PedidoPublicoController::class, 'store'])->middleware('throttle:5,60');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::get('/login/clave', [AuthController::class, 'clave'])->name('login.clave');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/auth/google', [AuthController::class, 'google'])->name('google');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect(auth()->user()->inicio()))->name('inicio');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/cuenta', [AuthController::class, 'cuenta'])->name('cuenta');
    Route::put('/cuenta', [AuthController::class, 'actualizarCuenta'])->name('cuenta.update');
    Route::post('/push', [PushController::class, 'suscribir'])->name('push');
    Route::post('/impersonar/salir', [UsuarioController::class, 'dejarDeImpersonar'])->name('impersonar.salir');

    // Portal: cualquier persona de la UNIQ registra y sigue sus pedidos
    Route::get('/mis-pedidos', [PortalController::class, 'index'])->name('portal');
    Route::get('/mis-pedidos/nuevo', [PortalController::class, 'create'])->name('portal.create');
    Route::post('/mis-pedidos', [PortalController::class, 'store'])->name('portal.store');
    Route::post('/areas', [TicketController::class, 'nuevaArea'])->name('areas.store');

    // Personal de la OTI
    Route::middleware('can:personal')->group(function () {
        Route::get('/bandeja', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/mios', [TicketController::class, 'mios'])->name('tickets.mios');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show')->whereNumber('ticket');
        Route::post('/tickets/{ticket}/estado', [TicketController::class, 'estado'])->name('tickets.estado');
        Route::post('/tickets/{ticket}/resolver', [TicketController::class, 'resolver'])->name('tickets.resolver');
        Route::post('/tickets/{ticket}/devolver', [TicketController::class, 'devolver'])->name('tickets.devolver');
        Route::get('/soluciones', [TicketController::class, 'soluciones'])->name('soluciones');
        Route::get('/panel', PanelController::class)->name('panel');
        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes');
        Route::get('/reportes/detalle', [ReporteController::class, 'detalle'])->name('reportes.detalle');
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
        Route::get('/derivaciones', [DerivacionController::class, 'index'])->name('derivaciones.index');
        Route::post('/derivaciones/{derivacion}/cerrar', [DerivacionController::class, 'cerrar'])->name('derivaciones.cerrar');
    });

    // Mesa de ayuda y administrador
    Route::middleware('can:asignar')->group(function () {
        Route::get('/tickets/nuevo', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::post('/tickets/{ticket}/asignar', [TicketController::class, 'asignar'])->name('tickets.asignar');
        Route::post('/tickets/{ticket}/validar', [TicketController::class, 'validar'])->name('tickets.validar');
        Route::post('/tickets/{ticket}/rechazar', [TicketController::class, 'rechazar'])->name('tickets.rechazar');
        Route::post('/tickets/{ticket}/apoyos', [TicketController::class, 'apoyos'])->name('tickets.apoyos');
        Route::post('/tickets/{ticket}/clasificar', [TicketController::class, 'clasificar'])->name('tickets.clasificar');
        Route::post('/tickets/{ticket}/reabrir', [TicketController::class, 'reabrir'])->name('tickets.reabrir');
        Route::post('/derivaciones', [DerivacionController::class, 'store'])->name('derivaciones.store');
        Route::get('/personas/buscar', [TicketController::class, 'buscarPersona'])->name('personas.buscar');

        Route::get('/directorio', [DirectorioController::class, 'index'])->name('directorio');
        Route::post('/directorio/personas', [DirectorioController::class, 'crearPersona'])->name('directorio.persona.store');
        Route::put('/directorio/personas/{solicitante}', [DirectorioController::class, 'persona'])->name('directorio.persona');
        Route::put('/directorio/areas/{area}', [DirectorioController::class, 'area'])->name('directorio.area');
    });

    // Administrador
    Route::middleware('can:jefe')->group(function () {
        Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
        Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show'])->name('usuarios.show');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::post('/usuarios/{usuario}/impersonar', [UsuarioController::class, 'impersonar'])->name('usuarios.impersonar');
    });
});
