@props(['etiqueta' => 'Fotografías'])
{{-- Hasta 3 fotos. El navegador las reduce a 850 px de ancho (alto proporcional) en WebP antes de subirlas. --}}
<div x-data="fotos()">
    <div class="label">{{ $etiqueta }} <span class="font-normal muted">(opcional, hasta 3)</span></div>
    <input type="file" name="fotos[]" x-ref="input" multiple accept="image/*" class="hidden">
    <input type="file" x-ref="selector" multiple accept="image/*" class="hidden" @change="agregar($event.target.files); $event.target.value = ''">

    <div class="flex flex-wrap gap-2.5">
        <template x-for="(f, i) in lista" :key="f.url">
            <div class="group relative size-28 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                <img :src="f.url" class="size-full object-cover" alt="">
                <span class="absolute bottom-1 left-1 rounded bg-neutral-900/70 px-1.5 py-0.5 text-[10px] font-medium text-white" x-text="f.peso"></span>
                <button type="button" @click="quitar(i)" class="absolute top-1 right-1 grid size-6 place-items-center rounded-full bg-white/90 text-neutral-700 shadow hover:bg-danger-50 hover:text-danger-600" aria-label="Quitar foto"><i class="ti ti-x text-sm"></i></button>
            </div>
        </template>
        <button type="button" x-show="lista.length < 3" @click="$refs.selector.click()" :disabled="procesando"
                class="flex size-28 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-neutral-300 text-neutral-500 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-600">
            <span x-show="procesando" class="spinner"></span>
            <i x-show="!procesando" class="ti ti-camera-plus text-2xl"></i>
            <span class="text-xs font-medium" x-text="procesando ? 'Comprimiendo…' : 'Agregar foto'"></span>
        </button>
    </div>
    <p class="hint">Se reducen automáticamente para ahorrar espacio.</p>
</div>
