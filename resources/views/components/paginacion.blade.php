@props(['items'])
{{-- Pie de listado: "Mostrando 1–10 de 45", filas por página (10/20/50/100) y páginas. --}}
@php
    $actual = $items->currentPage();
    $ultima = $items->lastPage();
    $paginas = collect(range(max(1, $actual - 2), min($ultima, $actual + 2)));
@endphp
<div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-neutral-100 px-4 py-3 text-sm">
    <span class="muted">
        @if ($items->total())
            Mostrando <b class="font-medium text-neutral-700">{{ $items->firstItem() }}–{{ $items->lastItem() }}</b> de <b class="font-medium text-neutral-700">{{ $items->total() }}</b>
        @else
            Sin resultados
        @endif
    </span>

    <form class="flex items-center gap-2" method="GET">
        @foreach (request()->except('por_pagina', 'page', 'parcial') as $k => $v)
            @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label for="por-pagina-{{ $items->getPageName() }}" class="muted">Filas</label>
        <select id="por-pagina-{{ $items->getPageName() }}" name="por_pagina" class="input !w-auto !py-1 !pr-8" onchange="this.form.requestSubmit()">
            @foreach (\App\Http\Controllers\Controller::POR_PAGINA as $n)
                <option value="{{ $n }}" @selected($items->perPage() === $n)>{{ $n }}</option>
            @endforeach
        </select>
    </form>

    @if ($ultima > 1)
        <nav class="ml-auto flex items-center gap-1" aria-label="Paginación">
            <a href="{{ $items->previousPageUrl() ?? '#' }}" @class(['btn btn-sm !px-2', 'pointer-events-none opacity-40' => $items->onFirstPage()]) aria-label="Anterior"><i class="ti ti-chevron-left"></i></a>
            @if ($paginas->first() > 1)
                <a href="{{ $items->url(1) }}" class="btn btn-sm btn-ghost">1</a>
                @if ($paginas->first() > 2)<span class="px-1 muted">…</span>@endif
            @endif
            @foreach ($paginas as $p)
                <a href="{{ $items->url($p) }}" @class(['btn btn-sm min-w-8', 'btn-primary' => $p === $actual, 'btn-ghost' => $p !== $actual]) @if ($p === $actual) aria-current="page" @endif>{{ $p }}</a>
            @endforeach
            @if ($paginas->last() < $ultima)
                @if ($paginas->last() < $ultima - 1)<span class="px-1 muted">…</span>@endif
                <a href="{{ $items->url($ultima) }}" class="btn btn-sm btn-ghost">{{ $ultima }}</a>
            @endif
            <a href="{{ $items->nextPageUrl() ?? '#' }}" @class(['btn btn-sm !px-2', 'pointer-events-none opacity-40' => ! $items->hasMorePages()]) aria-label="Siguiente"><i class="ti ti-chevron-right"></i></a>
        </nav>
    @endif
</div>
