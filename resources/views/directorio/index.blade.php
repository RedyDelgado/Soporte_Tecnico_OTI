@php
    $areasJs = $sedes->flatMap(fn ($s) => $s->areas->map(fn ($a) => ['id' => $a->id, 'nombre' => $s->nombre.' · '.$a->nombre]));
@endphp
<x-layout titulo="Directorio">
    <x-cabecera titulo="Directorio" subtitulo="Áreas por sede y las personas de cada oficina. Si alguien rota de área, edítala: sus pedidos anteriores se quedan donde se hicieron.">
        <button type="button" class="btn" onclick="document.getElementById('modal-nueva-area').showModal()"><i class="ti ti-building-plus"></i>Nueva área</button>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-nueva-persona').showModal()"><i class="ti ti-user-plus"></i>Nueva persona</button>
    </x-cabecera>

    <div class="grid gap-4 xl:grid-cols-[280px_1fr]" x-data="{ persona: null, area: null }">
        {{-- Áreas por sede --}}
        <div class="grid content-start gap-4 sm:grid-cols-2 xl:grid-cols-1">
            @foreach ($sedes as $s)
                <div class="card overflow-hidden">
                    <div class="card-header !py-3"><i class="ti ti-map-pin text-brand-500"></i><span class="card-title">{{ $s->nombre }}</span><span class="ml-auto text-xs muted">{{ $s->areas->count() }} áreas</span></div>
                    <ul class="divide-y divide-neutral-100">
                        @forelse ($s->areas as $a)
                            <li class="group flex items-center gap-2 px-4 py-2 hover:bg-neutral-50">
                                <a href="?area={{ $a->id }}" @class(['flex-1 truncate', 'font-semibold text-brand-700' => request('area') == $a->id])>{{ $a->nombre }}</a>
                                <span class="text-xs muted" title="Personas · pedidos">{{ $a->solicitantes_count }} · {{ $a->tickets_count }}</span>
                                <button type="button" class="rounded p-1 text-neutral-400 opacity-0 hover:bg-neutral-100 hover:text-neutral-700 group-hover:opacity-100 focus:opacity-100"
                                        @click="area = @js($a->only('id', 'nombre')); $refs.modalArea.showModal()" aria-label="Renombrar {{ $a->nombre }}"><i class="ti ti-pencil"></i></button>
                            </li>
                        @empty
                            <li class="px-4 py-3 text-xs muted">Sin áreas registradas.</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Personas --}}
        <div class="card self-start overflow-hidden">
            <form class="flex flex-wrap items-center gap-3 border-b border-neutral-100 px-4 py-3">
                <div class="relative min-w-60 flex-1">
                    <i class="ti ti-search absolute top-1/2 left-3 -translate-y-1/2 muted"></i>
                    <input name="q" value="{{ $q }}" class="input !pl-9" placeholder="Buscar por nombre, celular o área" aria-label="Buscar personas">
                </div>
                @if (request('area'))<input type="hidden" name="area" value="{{ request('area') }}">@endif
                <button class="btn"><i class="ti ti-search"></i>Buscar</button>
                @if ($q || request('area'))<a href="{{ route('directorio') }}" class="btn btn-ghost">Limpiar</a>@endif
            </form>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Persona</th><th>Sede y área actual</th><th>Celular</th><th class="text-right">Pedidos</th><th class="w-24"></th></tr></thead>
                    <tbody>
                        @forelse ($personas as $p)
                            @php
                                $datos = [
                                    'id' => $p->id, 'nombre' => $p->nombre, 'telefono' => $p->telefono, 'area_id' => $p->area_id,
                                    'historial' => $p->tickets->sortByDesc('id')->map(fn ($t) => ['codigo' => $t->codigo, 'url' => route('tickets.show', $t), 'area' => $t->area->nombre, 'fecha' => $t->created_at->format('d/m/Y'), 'actual' => $t->area_id === $p->area_id])->values(),
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ mb_substr($p->nombre, 0, 1) }}</span>
                                        <span class="font-medium whitespace-nowrap text-neutral-900">{{ $p->nombre }}</span>
                                    </div>
                                </td>
                                <td><div class="text-neutral-800">{{ $p->area->nombre }}</div><div class="text-xs muted">{{ $p->area->sede->nombre }}</div></td>
                                <td class="tabular-nums muted">{{ $p->telefono ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ $p->tickets->count() }}</td>
                                <td class="text-right"><button type="button" class="btn btn-sm" @click="persona = @js($datos); $refs.modalPersona.showModal()"><i class="ti ti-pencil"></i>Editar</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-12 text-center muted">No hay personas{{ $q ? ' para «'.$q.'»' : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-paginacion :items="$personas" />
        </div>

        <x-modal titulo="Editar persona" x-ref="modalPersona">
            <template x-if="persona">
                <form method="POST" :action="'{{ url('directorio/personas') }}/' + persona.id" class="space-y-4"
                      data-confirmar="¿Guardar los cambios de esta persona?" data-confirmar-boton="Guardar">
                    @csrf @method('PUT')
                    <div><label class="label" for="p-nombre">Nombre</label><input id="p-nombre" name="nombre" x-model="persona.nombre" class="input" required></div>
                    <div><label class="label" for="p-tel">Celular</label><input id="p-tel" name="telefono" x-model="persona.telefono" class="input"></div>
                    <div>
                        <label class="label" for="p-area">Área actual</label>
                        <select id="p-area" name="area_id" x-model="persona.area_id" class="input">
                            @foreach ($areasJs as $a)<option value="{{ $a['id'] }}">{{ $a['nombre'] }}</option>@endforeach
                        </select>
                        <p class="hint">Si cambia de área, solo se mueve hacia adelante: el historial no cambia.</p>
                    </div>
                    <div x-show="persona.historial.length">
                        <div class="label">Historial de pedidos</div>
                        <ul class="max-h-40 divide-y divide-neutral-100 overflow-y-auto rounded-lg border border-neutral-200">
                            <template x-for="h in persona.historial" :key="h.codigo">
                                <li class="flex items-center gap-2 px-3 py-1.5">
                                    <a :href="h.url" class="link font-mono text-xs" x-text="h.codigo"></a>
                                    <span class="flex-1 truncate text-xs" :class="h.actual ? '' : 'text-warn-700'" x-text="h.area"></span>
                                    <span class="text-xs muted" x-text="h.fecha"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                        <button type="button" class="btn" @click="$refs.modalPersona.close()">Cancelar</button>
                        <button class="btn btn-primary"><i class="ti ti-check"></i>Guardar</button>
                    </div>
                </form>
            </template>
        </x-modal>

        <x-modal titulo="Renombrar área" x-ref="modalArea">
            <template x-if="area">
                <form method="POST" :action="'{{ url('directorio/areas') }}/' + area.id" class="space-y-4">
                    @csrf @method('PUT')
                    <div><label class="label" for="a-nombre">Nombre</label><input id="a-nombre" name="nombre" x-model="area.nombre" class="input" required></div>
                    <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                        <button type="button" class="btn" @click="$refs.modalArea.close()">Cancelar</button>
                        <button class="btn btn-primary"><i class="ti ti-check"></i>Guardar</button>
                    </div>
                </form>
            </template>
        </x-modal>
    </div>

    <x-modal id="modal-nueva-persona" titulo="Nueva persona">
        <form method="POST" action="{{ route('directorio.persona.store') }}" class="space-y-4">
            @csrf
            <div><label class="label" for="np-nombre">Nombre completo</label><input id="np-nombre" name="nombre" class="input" required placeholder="María Quispe Huamán"></div>
            <div><label class="label" for="np-tel">Celular</label><input id="np-tel" name="telefono" class="input" inputmode="tel" placeholder="984123456"><p class="hint">Opcional, pero evita duplicados.</p></div>
            <div>
                <label class="label" for="np-area">Sede y área</label>
                <select id="np-area" name="area_id" class="input" required>
                    <option value="">Elegir…</option>
                    @foreach ($sedes as $s)
                        <optgroup label="{{ $s->nombre }}">
                            @foreach ($s->areas as $a)<option value="{{ $a->id }}">{{ $a->nombre }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="hint">¿No está el área? Créala primero con «Nueva área».</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                <button class="btn btn-primary"><i class="ti ti-check"></i>Registrar persona</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="modal-nueva-area" titulo="Nueva área">
        <form method="POST" action="{{ route('areas.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="na-sede">Sede</label>
                <select id="na-sede" name="sede_id" class="input" required>
                    @foreach ($sedes as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach
                </select>
            </div>
            <div><label class="label" for="na-nombre">Nombre del área</label><input id="na-nombre" name="nombre" class="input" required placeholder="Unidad de Abastecimiento"></div>
            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                <button class="btn btn-primary"><i class="ti ti-check"></i>Registrar área</button>
            </div>
        </form>
    </x-modal>
</x-layout>
