@props(['titulo' => null])
@php
    $u = auth()->user();
    // [ruta, icono, texto, visible]
    $personal = (bool) $u?->esPersonal();
    $menu = [
        'Soporte' => [
            ['portal', 'ti-message-report', 'Mis pedidos', (bool) $u],
            ['portal.create', 'ti-send', 'Pedir soporte', $u && ! $personal],
        ],
        'Operación' => [
            ['tickets.index', 'ti-inbox', 'Bandeja', $personal],
            ['tickets.create', 'ti-circle-plus', 'Nuevo ticket', $u?->puedeAsignar()],
            ['tickets.mios', 'ti-tool', 'Mis tickets', $personal && $u->rol !== 'mesa'],
        ],
        'Consulta' => [
            ['panel', 'ti-chart-bar', 'Panel', $personal],
            ['soluciones', 'ti-bulb', 'Soluciones', $personal],
            ['reportes', 'ti-report-analytics', 'Reportes', $u?->esJefe()],
        ],
        'Administración' => [
            ['directorio', 'ti-address-book', 'Directorio', $u?->puedeAsignar()],
            ['categorias.index', 'ti-tags', 'Categorías', $u?->esJefe()],
            ['usuarios.index', 'ti-users', 'Usuarios', $u?->esJefe()],
        ],
    ];
    $iniciales = $u ? collect(explode(' ', $u->name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') : '';
@endphp
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#173556">
    <title>{{ $titulo ? $titulo.' · ' : '' }}Soporte OTI</title>
    <link rel="icon" href="/logo-192.png">
    <link rel="apple-touch-icon" href="/logo-192.png">
    <link rel="manifest" href="/manifest.json">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div id="barra-carga"></div>

@auth
    <div x-data="{ menu: false }" @keydown.escape.window="menu = false" class="min-h-full lg:pl-64">

        {{-- Sidebar: menú principal --}}
        <div x-show="menu" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-neutral-900/40 lg:hidden" @click="menu = false"></div>
        <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-neutral-200 bg-white transition-transform duration-300 ease-drawer lg:translate-x-0">
            <a href="{{ route('inicio') }}" class="flex h-14 shrink-0 items-center gap-2.5 border-b border-neutral-100 px-5">
                <img src="/logo.webp" class="size-10" alt="Soporte OTI">
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-neutral-900">Soporte OTI</span>
                    <span class="block text-xs muted">Mesa de ayuda</span>
                </span>
            </a>
            <nav class="flex-1 overflow-y-auto px-3 pb-6">
                @foreach ($menu as $grupo => $items)
                    @php $items = array_filter($items, fn ($i) => $i[3]); @endphp
                    @if ($items)
                        <div class="nav-grupo">{{ $grupo }}</div>
                        <div class="space-y-0.5">
                            @foreach ($items as [$ruta, $icono, $texto])
                                <a href="{{ route($ruta) }}" @class(['nav-item', 'activo' => request()->routeIs($ruta)])>
                                    <i class="ti {{ $icono }}"></i>{{ $texto }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>
        </aside>

        {{-- Navbar: cuenta del usuario --}}
        <header class="sticky top-0 z-20 border-b border-neutral-200 bg-white/85 backdrop-blur">
            @if (session('impersonador'))
                <form method="POST" action="{{ route('impersonar.salir') }}" class="flex items-center justify-center gap-3 bg-warn-100 px-4 py-1.5 text-sm text-warn-800">
                    @csrf
                    <i class="ti ti-eye"></i>
                    <span>Estás viendo el sistema como <b>{{ $u->name }}</b> ({{ \App\Models\User::ROLES[$u->rol] }})</span>
                    <button class="btn btn-sm !border-warn-700 !bg-warn-700 !text-white">Volver a mi cuenta</button>
                </form>
            @endif
            <div class="flex h-14 items-center gap-3 px-3 sm:px-5">
                <button type="button" class="btn btn-ghost !px-2 lg:hidden" @click="menu = true" aria-label="Abrir menú"><i class="ti ti-menu-2 text-xl"></i></button>
                <div class="min-w-0 flex-1 truncate text-sm muted">{{ $titulo }}</div>

                <div x-data="avisos('{{ config('webpush.vapid.public_key') }}')" x-cloak>
                    <button x-show="soportado && !activo" @click="activar" class="btn btn-sm" title="Recibir avisos en este dispositivo">
                        <i class="ti ti-bell-off"></i><span class="hidden sm:inline">Activar avisos</span>
                    </button>
                    <span x-show="activo" class="grid size-9 place-items-center rounded-lg text-neutral-500" title="Avisos activos"><i class="ti ti-bell-ringing text-xl"></i></span>
                </div>

                <div x-data="{ abierto: false }" class="relative" @click.outside="abierto = false">
                    <button type="button" @click="abierto = !abierto" class="flex items-center gap-2.5 rounded-lg py-1 pr-2 pl-1 transition hover:bg-neutral-100" :aria-expanded="abierto">
                        <span class="grid size-8 place-items-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ $iniciales }}</span>
                        <span class="hidden text-left leading-tight sm:block">
                            <span class="block text-sm font-medium text-neutral-900">{{ $u->name }}</span>
                            <span class="block text-xs muted">{{ \App\Models\User::ROLES[$u->rol] }}</span>
                        </span>
                        <i class="ti ti-chevron-down muted"></i>
                    </button>
                    <div x-show="abierto" x-cloak x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-64 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg shadow-neutral-900/10">
                        <div class="border-b border-neutral-100 px-4 py-3">
                            <div class="text-sm font-medium text-neutral-900">{{ $u->name }}</div>
                            <div class="truncate text-xs muted">{{ $u->email }}</div>
                        </div>
                        <div class="p-1.5">
                            <a href="{{ route('cuenta') }}" class="nav-item"><i class="ti ti-user-circle"></i>Mi cuenta</a>
                            @if (in_array($u->rol, ['tecnico', 'jefe']))
                                <a href="{{ route('tickets.mios') }}" class="nav-item"><i class="ti ti-tool"></i>Mis tickets</a>
                            @endif
                            <a href="{{ route('portal') }}" class="nav-item"><i class="ti ti-message-report"></i>Mis pedidos de soporte</a>
                            <form method="POST" action="{{ route('logout') }}" data-confirmar="¿Cerrar sesión?" data-confirmar-boton="Cerrar sesión">
                                @csrf
                                <button class="nav-item w-full !text-danger-600 hover:!bg-danger-50"><i class="ti ti-logout !text-danger-500"></i>Cerrar sesión</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="px-3 py-4 sm:px-5 lg:py-5">
            {{ $slot }}
        </main>
    </div>
@else
    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot }}
    </main>
@endauth

    {{-- Notificaciones: arriba a la derecha, desaparecen solas --}}
    <div x-data class="pointer-events-none fixed top-3 right-3 z-50 flex w-80 max-w-[calc(100vw-1.5rem)] flex-col gap-2"
         x-init="@if (session('ok')) $store.toasts.push(@js(session('ok')), 'ok', {{ max(4000, mb_strlen(session('ok')) * 70) }}) @endif
                 @if ($errors->any() && auth()->check()) $store.toasts.push(@js($errors->first()), 'error', 7000) @endif">
        <template x-for="t in $store.toasts.lista" :key="t.id">
            <div class="pointer-events-auto flex items-start gap-2.5 rounded-lg border bg-white px-3.5 py-2.5 text-sm shadow-lg shadow-neutral-900/10 transition duration-200 ease-out starting:translate-x-4 starting:opacity-0 motion-reduce:starting:translate-x-0"
                 :class="{ 'border-ok-200': t.tipo === 'ok', 'border-danger-200': t.tipo === 'error', 'border-brand-200': t.tipo === 'info', 'translate-x-4 opacity-0 !duration-150 motion-reduce:translate-x-0': t.saliendo }">
                <i class="ti mt-0.5 text-base" :class="{ 'ti-circle-check text-ok-600': t.tipo === 'ok', 'ti-alert-circle text-danger-600': t.tipo === 'error', 'ti-bell-ringing text-brand-600': t.tipo === 'info' }"></i>
                <span class="flex-1 text-neutral-800" x-text="t.texto"></span>
                <button type="button" @click="$store.toasts.quitar(t.id)" class="text-neutral-400 hover:text-neutral-700" aria-label="Cerrar"><i class="ti ti-x"></i></button>
            </div>
        </template>
    </div>

    <dialog id="modal-confirmar" class="modal !max-w-sm">
        <form method="dialog" class="p-4">
            <div class="mb-4 grid size-10 place-items-center rounded-full bg-brand-50"><i class="ti ti-help text-xl text-brand-600"></i></div>
            <p data-mensaje class="text-base font-medium text-neutral-900"></p>
            <div class="mt-4 flex justify-end gap-2">
                <button value="" class="btn">Cancelar</button>
                <button value="ok" data-ok class="btn btn-primary">Confirmar</button>
            </div>
        </form>
    </dialog>
</body>
</html>
