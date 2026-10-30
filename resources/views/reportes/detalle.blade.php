<x-layout titulo="Detalle del reporte">
    <x-cabecera :titulo="$titulo" :subtitulo="'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y')" :volver="route('reportes', request()->only('desde', 'hasta', 'sede'))">
        <a href="{{ request()->fullUrlWithQuery(['csv' => 1]) }}" class="btn"><i class="ti ti-file-spreadsheet"></i>Descargar Excel</a>
    </x-cabecera>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabla min-w-[1000px]">
                <thead><tr><th class="w-8"></th><th>Código</th><th>Fecha</th><th>Sede y área</th><th>Solicitante</th><th>Problema</th><th>Estado</th><th>Técnico</th><th>Solución</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $t)
                        <tr>
                            <td><x-semaforo :ticket="$t" /></td>
                            <td class="whitespace-nowrap"><a href="{{ route('tickets.show', $t) }}" class="link font-mono">{{ $t->codigo }}</a></td>
                            <td class="whitespace-nowrap muted">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $t->area->nombre }}<div class="text-xs muted">{{ $t->sede->nombre }}</div></td>
                            <td>{{ $t->solicitante->nombre }}</td>
                            <td><div class="max-w-64 truncate">{{ $t->descripcion }}</div><div class="text-xs muted">{{ $t->categoria->nombre }}</div></td>
                            <td><x-etiqueta :estado="$t->estado" /></td>
                            <td>{{ $t->tecnico?->name ?? '—' }}</td>
                            <td class="max-w-64 truncate text-xs muted">{{ $t->solucion ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-10 text-center muted">Sin pedidos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :items="$tickets" />
    </div>
</x-layout>
