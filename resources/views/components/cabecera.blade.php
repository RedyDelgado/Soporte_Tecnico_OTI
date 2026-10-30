@props(['titulo', 'subtitulo' => null, 'volver' => null])
{{-- Encabezado de página: título, descripción y acciones a la derecha (slot). --}}
<div class="mb-4 flex flex-wrap items-end gap-3">
    <div class="min-w-0 flex-1">
        @if ($volver)
            <a href="{{ $volver }}" class="mb-2 inline-flex items-center gap-1 text-sm muted hover:text-neutral-900"><i class="ti ti-arrow-left"></i>Volver</a>
        @endif
        <h1>{{ $titulo }}</h1>
        @if ($subtitulo)<p class="mt-0.5 text-sm muted">{{ $subtitulo }}</p>@endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
