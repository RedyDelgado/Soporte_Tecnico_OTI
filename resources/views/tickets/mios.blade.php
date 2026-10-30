<x-layout titulo="Mis tickets">
    @php $prioridad = ['alta' => 'border-danger-200 bg-danger-50 text-danger-700', 'media' => 'border-warn-200 bg-warn-50 text-warn-700', 'baja' => '']; @endphp
    <x-cabecera titulo="Mi cola de trabajo" subtitulo="Ordenada por prioridad: alta primero y, dentro de cada una, lo que vence antes. Incluye los tickets donde vas de apoyo.">
        <span class="badge">{{ $abiertos->count() }} en cola</span>
    </x-cabecera>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($abiertos as $t)
            @php $apoyo = $t->tecnico_id !== auth()->id(); @endphp
            <a href="{{ route('tickets.show', $t) }}" @class([
                'card relative block p-4 transition hover:border-brand-300 hover:shadow-md',
                '!border-brand-400 ring-3 ring-brand-500/10' => $loop->first,
            ])>
                @if ($loop->first)
                    <span class="absolute -top-2.5 left-3 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-semibold text-white">Atender ahora</span>
                @endif
                <div class="flex items-center gap-2">
                    <span class="grid size-6 place-items-center rounded-full bg-neutral-100 text-xs font-semibold text-neutral-600">{{ $loop->iteration }}</span>
                    <x-semaforo :ticket="$t" />
                    <span class="font-mono text-sm font-semibold text-neutral-900">{{ $t->codigo }}</span>
                    <span class="badge capitalize {{ $prioridad[$t->prioridad] }}">{{ $t->prioridad }}</span>
                    <x-etiqueta :estado="$t->estado" class="ml-auto" />
                </div>
                <p class="mt-3 line-clamp-2 font-medium text-neutral-900">{{ $t->descripcion }}</p>
                <div class="mt-2 flex items-center gap-1.5 text-sm muted"><i class="ti ti-map-pin"></i>{{ $t->sede->nombre }} · {{ $t->area->nombre }}</div>
                @if ($apoyo || $t->apoyos->isNotEmpty())
                    <div class="mt-2 flex items-center gap-1.5 text-xs text-brand-700">
                        <i class="ti ti-users"></i>
                        {{ $apoyo ? 'Vas de apoyo de '.$t->tecnico->name : 'Con apoyo de '.$t->apoyos->pluck('name')->implode(', ') }}
                    </div>
                @endif
                <div class="mt-3 flex items-center justify-between border-t border-neutral-100 pt-3 text-xs">
                    <x-etiqueta :esp="$t->categoria->especialidad" />
                    <span class="muted">Vence {{ $t->vence_at->diffForHumans() }}</span>
                </div>
            </a>
        @empty
            <div class="card col-span-full px-4 py-16 text-center">
                <div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-ok-50"><i class="ti ti-coffee text-2xl text-ok-600"></i></div>
                <p class="font-medium text-neutral-900">No tienes tickets abiertos</p>
                <p class="text-sm muted">Te llegará un aviso cuando te asignen uno.</p>
            </div>
        @endforelse
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="card-header"><span class="card-title">Resueltos por mí</span></div>
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th class="w-8"></th><th>Código</th><th>Pedido</th><th>Área</th><th>Resuelto</th></tr></thead>
                <tbody>
                    @forelse ($resueltos as $t)
                        <tr>
                            <td><x-semaforo :ticket="$t" /></td>
                            <td class="whitespace-nowrap"><a href="{{ route('tickets.show', $t) }}" class="link font-mono">{{ $t->codigo }}</a></td>
                            <td class="max-w-96 truncate">{{ $t->descripcion }}</td>
                            <td class="muted">{{ $t->area->nombre }}</td>
                            <td class="whitespace-nowrap muted">{{ $t->resuelto_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center muted">Aún no has resuelto tickets.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :items="$resueltos" />
    </div>
</x-layout>
