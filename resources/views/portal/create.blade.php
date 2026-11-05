@php
    $sedesJs = $sedes->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'areas' => $s->areas->map->only('id', 'nombre')->values()]);
    $inicial = [
        'persona' => null, 'nombre' => auth()->user()->name, 'telefono' => old('telefono', $persona?->telefono ?? ''),
        'sede_id' => old('sede_id', $persona?->area->sede_id ?? ''), 'area_id' => old('area_id', $persona?->area_id ?? ''),
    ];
@endphp
<x-layout titulo="Nuevo pedido">
    <x-cabecera titulo="¿En qué te ayudamos?" subtitulo="Describe el problema; la mesa de ayuda lo asignará al especialista." :volver="route('portal')" />
    <x-estado-equipo class="mb-4" />

    <form method="POST" action="{{ route('portal.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="grid gap-4 p-4 lg:grid-cols-2" x-data="registro(@js($sedesJs), @js($inicial))">
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2">
                    <i class="ti ti-user-check text-lg text-brand-600"></i>
                    <div><span class="font-medium text-neutral-900">{{ auth()->user()->name }}</span> <span class="muted">· {{ auth()->user()->email }}</span></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="sede_id">Sede donde trabajas</label>
                        <select id="sede_id" name="sede_id" x-model="sede_id" @change="area_id = ''" class="input" required>
                            <option value="">Elegir…</option>
                            <template x-for="s in sedes" :key="s.id"><option :value="s.id" x-text="s.nombre" :selected="s.id == sede_id"></option></template>
                        </select>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label class="label !mb-0" for="area_id">Tu área u oficina</label>
                            <button type="button" x-show="sede_id" @click="$refs.modalArea.showModal()" class="link text-xs"><i class="ti ti-plus"></i> No está</button>
                        </div>
                        <x-combo-area />
                        @error('area_id')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="label" for="telefono">Celular <span class="font-normal muted">(para coordinar la visita)</span></label>
                    <input id="telefono" name="telefono" x-model="telefono" class="input" inputmode="tel" placeholder="984123456">
                    @error('telefono')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="categoria_id">Tipo de problema</label>
                    <select id="categoria_id" name="categoria_id" class="input">
                        <option value="">No estoy seguro</option>
                        @foreach ($categorias->groupBy('especialidad') as $esp => $grupo)
                            <optgroup label="{{ \App\Models\Categoria::ESPECIALIDADES[$esp] }}">
                                @foreach ($grupo as $c)<option value="{{ $c->id }}" @selected(old('categoria_id') == $c->id)>{{ $c->nombre }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <x-modal titulo="Mi área no está en la lista" x-ref="modalArea">
                    <div class="space-y-4">
                        <p class="muted">Se registrará en la sede <b class="text-neutral-900" x-text="sedes.find(s => s.id == sede_id)?.nombre"></b>.</p>
                        <div>
                            <label class="label" for="area-nombre">Nombre del área u oficina</label>
                            <input id="area-nombre" x-model="nuevaArea" class="input" placeholder="Unidad de Abastecimiento" @keydown.enter.prevent="guardarArea">
                            <p x-show="errorArea" x-text="errorArea" class="error"></p>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                            <button type="button" class="btn" @click="$refs.modalArea.close()">Cancelar</button>
                            <button type="button" class="btn btn-primary" @click="guardarArea" :disabled="guardandoArea">
                                <span x-show="guardandoArea" class="spinner"></span><i x-show="!guardandoArea" class="ti ti-check"></i>Registrar área
                            </button>
                        </div>
                    </div>
                </x-modal>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="label" for="descripcion">¿Qué pasa?</label>
                    <textarea id="descripcion" name="descripcion" rows="5" class="input" required placeholder="La impresora de mi oficina saca las hojas en blanco desde ayer">{{ old('descripcion') }}</textarea>
                    @error('descripcion')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-fotos etiqueta="Fotos del problema" />
                @error('fotos')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5">
            <a href="{{ route('portal') }}" class="btn">Cancelar</a>
            <button class="btn btn-primary"><i class="ti ti-send"></i>Enviar pedido</button>
        </div>
    </form>
</x-layout>
