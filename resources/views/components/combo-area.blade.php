@props(['deshabilitado' => '!sede_id', 'requerido' => 'true'])
{{-- Combobox de áreas con búsqueda. Lee areas, area_id y sede_id del x-data padre. --}}
<div {{ $attributes->class('relative') }} x-data="comboArea" @click.outside="cerrar()">
    <input type="hidden" name="area_id" :value="area_id" :disabled="{{ $deshabilitado }}">
    <input id="area_id" type="text" x-model="q" class="input pr-8" autocomplete="off" role="combobox" :aria-expanded="abierto"
           :disabled="{{ $deshabilitado }}" :required="{{ $requerido }}"
           :placeholder="sede_id ? 'Escribe para buscar…' : 'Primero elige la sede'"
           @focus="abrir()" @input="abrir(); idx = 0" @blur="setTimeout(() => cerrar(), 150)"
           @keydown.down.prevent="abrir(); idx = Math.min(idx + 1, filtradas.length - 1)"
           @keydown.up.prevent="idx = Math.max(idx - 1, 0)"
           @keydown.enter="if (abierto && filtradas[idx]) { $event.preventDefault(); elegir(filtradas[idx]) }"
           @keydown.escape="cerrar()">
    <i class="ti ti-selector pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-neutral-400"></i>
    <ul x-show="abierto" x-cloak role="listbox" class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-neutral-200 bg-white py-1 text-sm shadow-lg">
        <template x-for="(a, i) in filtradas" :key="a.id">
            <li role="option" :aria-selected="a.id == area_id" @mousedown.prevent="elegir(a)" @mouseenter="idx = i"
                class="cursor-pointer px-3 py-1.5" :class="i === idx ? 'bg-brand-50 text-brand-800' : 'text-neutral-700'" x-text="a.nombre"></li>
        </template>
        <li x-show="!filtradas.length" class="px-3 py-1.5 text-neutral-400">Sin coincidencias</li>
    </ul>
</div>
