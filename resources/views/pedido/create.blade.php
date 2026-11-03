@php
    $sedesJs = $sedes->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'areas' => $s->areas->map->only('id', 'nombre')->values()]);
@endphp
<x-layout titulo="Pedir soporte">
    <div class="mx-auto max-w-3xl py-4 sm:py-8">
        <div class="mb-4 flex items-center gap-3">
            <img src="/logo.webp" class="size-14" alt="Soporte OTI">
            <div>
                <h1>Pedir soporte a la OTI</h1>
                <p class="text-sm muted">Para quienes no tienen correo institucional. La mesa de ayuda valida tu pedido antes de atenderlo.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('pedido.publico') }}" enctype="multipart/form-data" class="card"
              x-data="{ sedes: @js($sedesJs), sede_id: '{{ old('sede_id') }}', area_id: '{{ old('area_id') }}', otra: {{ old('area_nueva') ? 'true' : 'false' }},
                        get areas() { return this.sedes.find(s => s.id == this.sede_id)?.areas ?? [] } }">
            @csrf
            {{-- Campo trampa para bots: las personas no lo ven --}}
            <div class="hidden" aria-hidden="true"><label>Sitio web <input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>

            <div class="grid gap-4 p-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="nombre">Nombre completo</label>
                    <input id="nombre" name="nombre" value="{{ old('nombre') }}" class="input" required maxlength="120" autocomplete="name" placeholder="María Quispe Huamán">
                    @error('nombre')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="telefono">Celular</label>
                    <input id="telefono" name="telefono" value="{{ old('telefono') }}" class="input" required inputmode="tel" autocomplete="tel" placeholder="984123456">
                    <p class="hint">La mesa de ayuda te llamará o escribirá a este número.</p>
                    @error('telefono')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="email">Correo personal <span class="font-normal muted">(opcional)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" autocomplete="email" placeholder="maria@gmail.com">
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="sede_id">Sede</label>
                    <select id="sede_id" name="sede_id" x-model="sede_id" @change="area_id = ''" class="input" required>
                        <option value="">Elegir…</option>
                        <template x-for="s in sedes" :key="s.id"><option :value="s.id" x-text="s.nombre" :selected="s.id == sede_id"></option></template>
                    </select>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="label !mb-0" for="area_id">Área u oficina</label>
                        <button type="button" x-show="sede_id" @click="otra = !otra; area_id = ''" class="link text-xs" x-text="otra ? 'Elegir de la lista' : 'No está en la lista'"></button>
                    </div>
                    <select x-show="!otra" id="area_id" name="area_id" x-model="area_id" class="input" :disabled="!sede_id || otra" :required="!otra">
                        <option value="" x-text="sede_id ? 'Elegir…' : 'Primero elige la sede'"></option>
                        <template x-for="a in areas" :key="a.id"><option :value="a.id" x-text="a.nombre" :selected="a.id == area_id"></option></template>
                    </select>
                    <input x-show="otra" x-cloak name="area_nueva" value="{{ old('area_nueva') }}" class="input" :disabled="!otra" :required="otra" maxlength="80" placeholder="Nombre de tu área u oficina">
                    @error('area_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="descripcion">¿Qué pasa?</label>
                    <textarea id="descripcion" name="descripcion" rows="4" class="input" required maxlength="2000" placeholder="La computadora de mi oficina no enciende desde esta mañana">{{ old('descripcion') }}</textarea>
                    @error('descripcion')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <x-fotos etiqueta="Fotos del problema" />
                    @error('fotos')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-b-xl border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5">
                <a href="{{ route('login') }}" class="link text-sm">¿Tienes correo UNIQ? Ingresa aquí</a>
                <button class="btn btn-primary"><i class="ti ti-send"></i>Enviar pedido</button>
            </div>
        </form>
    </div>
</x-layout>
