<x-layout titulo="Soluciones">
    <x-cabecera titulo="Base de soluciones" subtitulo="Busca cómo se resolvieron casos parecidos antes." />

    <form class="mb-4">
        <div class="relative">
            <i class="ti ti-search absolute top-1/2 left-4 -translate-y-1/2 text-lg muted"></i>
            <input name="q" value="{{ $q }}" class="input !py-2 !pl-10" placeholder="impresora hojas en blanco" autofocus aria-label="Buscar soluciones">
        </div>
    </form>

    @if ($resultados)
        <div class="card overflow-hidden">
            <div class="divide-y divide-neutral-100">
                @forelse ($resultados as $t)
                    <a href="{{ route('tickets.show', $t) }}" class="block p-4 transition hover:bg-neutral-50/70">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-mono font-semibold text-brand-700">{{ $t->codigo }}</span>
                            <x-etiqueta :esp="$t->categoria->especialidad" />
                            <span class="muted">{{ $t->categoria->nombre }} · {{ $t->area->nombre }}</span>
                            <span class="ml-auto text-xs muted">{{ $t->tecnico?->name }} · {{ $t->resuelto_at->format('d/m/Y') }}</span>
                        </div>
                        <p class="mt-2 font-medium text-neutral-900">{{ $t->descripcion }}</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg bg-neutral-50 p-3"><div class="text-xs font-medium muted">Problema</div><p class="mt-1">{{ $t->diagnostico }}</p></div>
                            <div class="rounded-lg bg-ok-50 p-3"><div class="text-xs font-medium text-ok-700">Solución</div><p class="mt-1">{{ $t->solucion }}</p></div>
                        </div>
                    </a>
                @empty
                    <div class="py-12 text-center muted">No hay soluciones para «{{ $q }}».</div>
                @endforelse
            </div>
            <x-paginacion :items="$resultados" />
        </div>
    @endif
</x-layout>
