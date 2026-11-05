@props(['deshabilitado' => '!sede_id', 'requerido' => 'true', 'otro', 'otroActivo' => 'false'])
{{--
    Lista desplegable de áreas con buscador arriba y "Otro" al final. Lee areas, area_id y sede_id del x-data padre.
    otro: expresión Alpine que se ejecuta al elegir "Otro". otroActivo: expresión que indica que "Otro" está elegido.
--}}
<div {{ $attributes->class('relative') }} x-data="comboArea" @click.outside="cerrar()" @keydown.escape.prevent.stop="cerrar(); $refs.boton.focus()">
    <input type="hidden" name="area_id" :value="area_id" :disabled="{{ $deshabilitado }}">
    {{-- Campo invisible solo para que el navegador exija elegir un área. --}}
    <input tabindex="-1" aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-px opacity-0"
           :value="area_id || ({{ $otroActivo }} ? 'otro' : '')" :required="{{ $requerido }}" :disabled="{{ $deshabilitado }}">
    <button id="area_id" type="button" x-ref="boton" class="input flex items-center justify-between gap-2 text-left" role="combobox" :aria-expanded="abierto"
            :disabled="{{ $deshabilitado }}" @click="abierto ? cerrar() : abrir()" @keydown.down.prevent="abrir()">
        <span class="truncate" :class="nombre || {{ $otroActivo }} ? '' : 'text-neutral-400'"
              x-text="nombre || ({{ $otroActivo }} ? 'Otro' : (sede_id ? 'Elegir área' : 'Primero elige la sede'))"></span>
        <i class="ti ti-selector text-neutral-400"></i>
    </button>
    <div x-show="abierto" x-cloak class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
        <div class="border-b border-neutral-100 p-2">
            <div class="relative">
                <i class="ti ti-search pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-400"></i>
                <input x-ref="buscar" x-model="q" type="text" class="input pl-8" placeholder="Buscar oficina…" autocomplete="off"
                       @input="idx = 0"
                       @keydown.down.prevent="mover(1)" @keydown.up.prevent="mover(-1)"
                       @keydown.enter.prevent="idx < filtradas.length ? elegir(filtradas[idx]) : elegirOtro()">
            </div>
        </div>
        <ul role="listbox" class="max-h-60 overflow-auto py-1 text-sm">
            <template x-for="(a, i) in filtradas" :key="a.id">
                <li role="option" :aria-selected="a.id == area_id" @click="elegir(a)" @mouseenter="idx = i"
                    class="cursor-pointer px-3 py-1.5" :class="i === idx ? 'bg-brand-50 text-brand-800' : 'text-neutral-700'" x-text="a.nombre"></li>
            </template>
            <li x-show="!filtradas.length" class="px-3 py-1.5 text-neutral-400">Sin coincidencias</li>
            <li role="option" @click="elegirOtro()" @mouseenter="idx = filtradas.length"
                class="mt-1 cursor-pointer border-t border-neutral-100 px-3 py-1.5 font-medium"
                :class="idx === filtradas.length ? 'bg-brand-50 text-brand-800' : 'text-neutral-700'"><i class="ti ti-plus"></i> Otro</li>
        </ul>
    </div>
    <span x-ref="otro" class="hidden" @otro-area="{{ $otro }}"></span>
</div>
