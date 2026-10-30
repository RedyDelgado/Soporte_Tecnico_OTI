<x-layout titulo="Categorías">
    <x-cabecera titulo="Categorías" subtitulo="Los técnicos pueden crear categorías al registrar o cerrar un ticket. Revisa las nuevas, corrige nombres o desactiva las duplicadas.">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-categoria').showModal()"><i class="ti ti-plus"></i>Nueva categoría</button>
    </x-cabecera>

    <x-modal id="modal-categoria" titulo="Nueva categoría">
        <form method="POST" action="{{ route('categorias.store') }}" class="space-y-4">
            @csrf
            <div><label class="label" for="nc-nombre">Nombre</label><input id="nc-nombre" name="nombre" class="input" required maxlength="80" placeholder="Publicación de normas en gob.pe"></div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="nc-esp">Especialidad que la atiende</label>
                    <select id="nc-esp" name="especialidad" class="input">
                        @foreach (\App\Models\Categoria::ESPECIALIDADES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="nc-pri">Prioridad por defecto</label>
                    <select id="nc-pri" name="prioridad" class="input">
                        @foreach (['alta' => 'Alta · 4 h', 'media' => 'Media · 24 h', 'baja' => 'Baja · 72 h'] as $k => $v)<option value="{{ $k }}" @selected($k === 'media')>{{ $v }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                <button class="btn btn-primary"><i class="ti ti-check"></i>Crear categoría</button>
            </div>
        </form>
    </x-modal>

    <div class="card overflow-hidden">
        <form class="flex items-center gap-3 border-b border-neutral-100 px-4 py-3">
            <div class="relative max-w-sm flex-1">
                <i class="ti ti-search absolute top-1/2 left-3 -translate-y-1/2 muted"></i>
                <input name="q" value="{{ request('q') }}" class="input !pl-9" placeholder="Buscar categoría" aria-label="Buscar categoría">
            </div>
            <button class="btn">Buscar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="tabla min-w-[760px]">
                <thead><tr><th>Nombre</th><th class="w-48">Especialidad</th><th class="w-32">Prioridad</th><th class="w-24">Activa</th><th class="w-28"></th></tr></thead>
                <tbody>
                    @forelse ($categorias as $c)
                        <tr>
                            <td>
                                <input form="cat-{{ $c->id }}" name="nombre" value="{{ $c->nombre }}" class="input" aria-label="Nombre">
                                <p class="hint">
                                    {{ $c->tickets_count }} tickets
                                    @if ($c->creador) · creada por {{ $c->creador->name }} {{ $c->created_at->diffForHumans() }}@endif
                                    @if ($c->creador && $c->created_at->gt(now()->subDays(7)))<span class="badge ml-1 border-warn-200 bg-warn-50 text-warn-700">nueva</span>@endif
                                </p>
                            </td>
                            <td>
                                <select form="cat-{{ $c->id }}" name="especialidad" class="input" aria-label="Especialidad">
                                    @foreach (\App\Models\Categoria::ESPECIALIDADES as $k => $v)<option value="{{ $k }}" @selected($c->especialidad === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </td>
                            <td>
                                <select form="cat-{{ $c->id }}" name="prioridad" class="input" aria-label="Prioridad">
                                    @foreach (['alta', 'media', 'baja'] as $p)<option value="{{ $p }}" @selected($c->prioridad === $p)>{{ ucfirst($p) }}</option>@endforeach
                                </select>
                            </td>
                            <td><input form="cat-{{ $c->id }}" type="checkbox" name="activa" value="1" @checked($c->activa) class="size-4 accent-brand-600" aria-label="Activa"></td>
                            <td class="text-right">
                                <form id="cat-{{ $c->id }}" method="POST" action="{{ route('categorias.update', $c) }}">@csrf @method('PUT')
                                    <button class="btn btn-sm"><i class="ti ti-device-floppy"></i>Guardar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center muted">Sin categorías.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :items="$categorias" />
    </div>
</x-layout>
