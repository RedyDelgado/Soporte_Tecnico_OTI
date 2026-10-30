{{-- Selector de categoría con "Nueva categoría" en un modal. Requiere $categorias; opcionales $actual y $extra (atributos del select). --}}
<div x-data="categoriaNueva()">
    <div class="mb-1.5 flex items-center justify-between">
        <label class="label !mb-0" for="categoria_id">Categoría</label>
        <button type="button" @click="$refs.modal.showModal()" class="link text-xs"><i class="ti ti-plus"></i> Nueva categoría</button>
    </div>
    <select id="categoria_id" name="categoria_id" x-ref="select" class="input" required {!! $extra ?? '' !!}>
        <option value="">Elegir…</option>
        @foreach ($categorias->groupBy('especialidad') as $esp => $grupo)
            <optgroup label="{{ \App\Models\Categoria::ESPECIALIDADES[$esp] }}">
                @foreach ($grupo as $c)
                    <option value="{{ $c->id }}" data-prioridad="{{ $c->prioridad }}" @selected(old('categoria_id', $actual ?? null) == $c->id)>{{ $c->nombre }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    @error('categoria_id')<p class="error">{{ $message }}</p>@enderror

    <x-modal titulo="Nueva categoría" x-ref="modal">
        <div class="space-y-4">
            <div>
                <label class="label" for="cat-nombre">Nombre</label>
                <input id="cat-nombre" x-model="nombre" class="input" placeholder="Publicación de normas en gob.pe" @keydown.enter.prevent="guardar">
            </div>
            <div>
                <label class="label" for="cat-esp">Especialidad que la atiende</label>
                <select id="cat-esp" x-model="especialidad" class="input">
                    @foreach (\App\Models\Categoria::ESPECIALIDADES as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <p x-show="error" x-text="error" class="error"></p>
            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="btn" @click="$refs.modal.close()">Cancelar</button>
                <button type="button" class="btn btn-primary" @click="guardar" :disabled="guardando">
                    <span x-show="guardando" class="spinner"></span><i x-show="!guardando" class="ti ti-check"></i>Crear categoría
                </button>
            </div>
        </div>
    </x-modal>
</div>
