<x-layout titulo="Bandeja">
    <div x-data="bandeja({{ $resumen['max'] }})" @focusin="pausa = true" @focusout="pausa = false"
         @if (auth()->user()->puedeAsignar()) @keydown.window.n="if (!['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName) && !document.querySelector('dialog[open]')) location = '{{ route('tickets.create') }}'" @endif>

        <x-cabecera titulo="Bandeja de pedidos" subtitulo="Los pedidos nuevos aparecen solos; se actualiza cada 10 segundos.">
            <span class="flex items-center gap-1.5 text-xs font-medium text-ok-600">
                <span x-show="!actualizando" class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-ok-500 opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-ok-500"></span></span>
                <span x-show="actualizando" x-cloak class="spinner !size-3"></span>
                En vivo
            </span>
            @if (auth()->user()->puedeAsignar())
                <a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="ti ti-plus"></i>Nuevo ticket<kbd class="ml-1 hidden rounded bg-brand-500 px-1.5 text-[10px] sm:inline">N</kbd></a>
            @endif
        </x-cabecera>

        <div x-ref="contenido">
            @include('tickets._bandeja')
        </div>
    </div>
    @includeWhen(auth()->user()->puedeAsignar(), 'tickets._rechazar')
</x-layout>
