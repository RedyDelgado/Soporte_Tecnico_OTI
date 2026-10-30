<x-layout titulo="Mis pedidos">
    <x-cabecera titulo="Mis pedidos de soporte" subtitulo="Registra un problema y sigue su avance hasta que se resuelva.">
        <a href="{{ route('portal.create') }}" class="btn btn-primary"><i class="ti ti-plus"></i>Nuevo pedido</a>
    </x-cabecera>

    <x-estado-equipo class="mb-4" />

    @if (! $pedidos || $pedidos->total() === 0)
        <div class="card px-4 py-14 text-center">
            <div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-brand-50"><i class="ti ti-message-report text-2xl text-brand-600"></i></div>
            <p class="font-medium text-neutral-900">Aún no registraste pedidos</p>
            <p class="mb-4 text-sm muted">¿Algo no funciona? Cuéntanos y la OTI lo atenderá.</p>
            <a href="{{ route('portal.create') }}" class="btn btn-primary"><i class="ti ti-plus"></i>Registrar mi primer pedido</a>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Código</th><th>Pedido</th><th>Área</th><th>Estado</th><th>Atiende</th><th>Registrado</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pedidos as $t)
                            <tr>
                                <td class="font-mono font-semibold whitespace-nowrap text-neutral-900">{{ $t->codigo }}</td>
                                <td class="max-w-96">
                                    <div class="truncate">{{ $t->descripcion }}</div>
                                    <x-mensaje-pedido :ticket="$t" class="mt-1.5 !py-1 text-xs" />
                                </td>
                                <td class="muted">{{ $t->area->nombre }}</td>
                                <td><x-etiqueta :estado="$t->estado" /></td>
                                <td>{{ $t->tecnico?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap muted">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-right"><a href="{{ route('seguimiento', $t->token) }}" class="btn btn-sm"><i class="ti ti-eye"></i>Ver avance</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginacion :items="$pedidos" />
        </div>
    @endif
</x-layout>
