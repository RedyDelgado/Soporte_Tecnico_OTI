@php
    $sedesJs = $sedes->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'areas' => $s->areas->map->only('id', 'nombre')->values()]);
    $personaOld = old('solicitante_id') ? \App\Models\Solicitante::with('area')->find(old('solicitante_id')) : null;
    $inicial = [
        'persona' => $personaOld ? ['id' => $personaOld->id, 'nombre' => $personaOld->nombre, 'area_id' => $personaOld->area_id, 'area' => $personaOld->area->nombre] : null,
        'nombre' => old('nombre', ''), 'telefono' => old('telefono', ''),
        'sede_id' => old('sede_id', ''), 'area_id' => old('area_id', ''),
    ];
@endphp
<x-layout titulo="Nuevo ticket">
    <div>
        <x-cabecera titulo="Registrar pedido" subtitulo="Busca primero a la persona para no registrarla dos veces." :volver="route('tickets.index')" />

        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="grid items-start gap-4 xl:grid-cols-2"
              x-data="{ canal: '{{ old('canal', 'whatsapp') }}', prioridad: '{{ old('prioridad', 'media') }}' }">
            @csrf

            {{-- 1. Quién pide --}}
            <section class="card" x-data="registro(@js($sedesJs), @js($inicial))">
                <div class="card-header">
                    <span class="grid size-7 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span>
                    <span class="card-title">Solicitante</span>
                </div>
                <div class="space-y-4 p-4">
                    <input type="hidden" name="solicitante_id" :value="persona?.id ?? ''">

                    <div x-show="!persona" class="relative">
                        <label class="label" for="buscador">Buscar persona por nombre o celular</label>
                        <div class="relative">
                            <i class="ti ti-search absolute top-1/2 left-3 -translate-y-1/2 muted"></i>
                            <input id="buscador" x-ref="buscador" x-model="q" @input="buscar" class="input !pl-9" placeholder="Ana Quispe o 984111222" autocomplete="off">
                            <span x-show="buscando" class="spinner absolute top-2.5 right-3 text-brand-500"></span>
                        </div>
                        <div x-show="q.trim().length >= 2 && !buscando" x-cloak class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg shadow-neutral-900/10">
                            <template x-for="p in resultados" :key="p.id">
                                <button type="button" @click="elegir(p)" class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-brand-50">
                                    <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700" x-text="p.nombre[0]"></span>
                                    <span class="flex-1"><span class="font-medium text-neutral-900" x-text="p.nombre"></span><br><span class="text-xs muted" x-text="p.area + ' · ' + p.sede + (p.telefono ? ' · ' + p.telefono : '')"></span></span>
                                </button>
                            </template>
                            <button type="button" @click="nueva" class="flex w-full items-center gap-2 border-t border-neutral-100 px-3 py-2.5 text-left font-medium text-brand-700 hover:bg-brand-50">
                                <i class="ti ti-user-plus"></i>Registrar a «<span x-text="q.trim()"></span>» como persona nueva
                            </button>
                        </div>
                    </div>

                    <div x-show="persona" x-cloak class="flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2.5">
                        <i class="ti ti-user-check text-xl text-brand-600"></i>
                        <div class="flex-1"><span class="font-medium text-neutral-900" x-text="persona?.nombre"></span> <span class="muted">· registrada en <span x-text="persona?.area"></span></span></div>
                        <button type="button" @click="cambiarPersona" class="link text-xs">Cambiar</button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="nombre">Nombre completo</label>
                            <input id="nombre" name="nombre" x-ref="nombre" x-model="nombre" class="input" required placeholder="María Quispe Huamán">
                            @error('nombre')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="telefono">Celular</label>
                            <input id="telefono" name="telefono" x-model="telefono" class="input" inputmode="tel" placeholder="984123456">
                            <p class="hint">Con el celular el sistema reconoce a la persona y no la duplica.</p>
                        </div>
                        <div>
                            <label class="label" for="sede_id">Sede</label>
                            <select id="sede_id" name="sede_id" x-model="sede_id" @change="area_id = ''" class="input" required>
                                <option value="">Elegir…</option>
                                <template x-for="s in sedes" :key="s.id"><option :value="s.id" x-text="s.nombre" :selected="s.id == sede_id"></option></template>
                            </select>
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label class="label !mb-0" for="area_id">Área u oficina</label>
                                <button type="button" x-show="sede_id" @click="$refs.modalArea.showModal()" class="link text-xs"><i class="ti ti-plus"></i> Nueva área</button>
                            </div>
                            <select id="area_id" name="area_id" x-model="area_id" class="input" required :disabled="!sede_id">
                                <option value="" x-text="sede_id ? 'Elegir…' : 'Primero elige la sede'"></option>
                                <template x-for="a in areas" :key="a.id"><option :value="a.id" x-text="a.nombre" :selected="a.id == area_id"></option></template>
                            </select>
                            @error('area_id')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div x-show="rota" x-cloak class="flex gap-2 rounded-lg border border-warn-200 bg-warn-50 px-3 py-2.5 text-warn-800">
                        <i class="ti ti-arrows-exchange text-lg"></i>
                        <p><b x-text="persona?.nombre"></b> pasará de <b x-text="persona?.area"></b> a <b x-text="areaNombre"></b>. Sus tickets anteriores se quedan registrados en <span x-text="persona?.area"></span>.</p>
                    </div>
                </div>

                <x-modal titulo="Nueva área" x-ref="modalArea">
                    <div class="space-y-4">
                        <p class="muted">Se registrará en la sede <b class="text-neutral-900" x-text="sedes.find(s => s.id == sede_id)?.nombre"></b>.</p>
                        <div>
                            <label class="label" for="area-nombre">Nombre del área</label>
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
            </section>

            {{-- 2. Qué pide --}}
            <section class="card">
                <div class="card-header">
                    <span class="grid size-7 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">2</span>
                    <span class="card-title">Pedido</span>
                </div>
                <div class="space-y-4 p-4">
                    <fieldset>
                        <legend class="label">Canal por donde llegó</legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach (['whatsapp' => 'ti-brand-whatsapp', 'correo' => 'ti-mail', 'llamada' => 'ti-phone', 'presencial' => 'ti-user'] as $k => $icono)
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2.5 font-medium transition"
                                       :class="canal === '{{ $k }}' ? 'border-brand-500 bg-brand-50 text-brand-700 ring-3 ring-brand-500/10' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'">
                                    <input type="radio" name="canal" value="{{ $k }}" x-model="canal" class="sr-only">
                                    <i class="ti {{ $icono }} text-lg"></i>{{ \App\Models\Ticket::CANALES[$k] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                        @include('tickets._categoria', ['extra' => '@change="prioridad = $event.target.selectedOptions[0]?.dataset.prioridad || prioridad"'])
                        <fieldset>
                            <legend class="label">Prioridad</legend>
                            <div class="flex gap-1 rounded-lg bg-neutral-100 p-1">
                                @foreach (['alta' => 'text-danger-600', 'media' => 'text-warn-700', 'baja' => 'text-ok-600'] as $p => $c)
                                    <label class="cursor-pointer rounded-md px-3 py-1.5 capitalize transition"
                                           :class="prioridad === '{{ $p }}' ? 'bg-white shadow-sm font-semibold {{ $c }}' : 'text-neutral-500 hover:text-neutral-800'">
                                        <input type="radio" name="prioridad" value="{{ $p }}" x-model="prioridad" class="sr-only">{{ $p }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="hint">Plazo: alta 4 h · media 24 h · baja 72 h</p>
                        </fieldset>
                    </div>

                    <div>
                        <label class="label" for="descripcion">¿Qué necesita?</label>
                        <textarea id="descripcion" name="descripcion" rows="2" class="input" required placeholder="La impresora saca las hojas en blanco">{{ old('descripcion') }}</textarea>
                        @error('descripcion')<p class="error">{{ $message }}</p>@enderror
                    </div>

                    <x-fotos etiqueta="Fotos del problema" />

                    <div class="border-t border-neutral-100 pt-5">
                        <label class="label" for="tecnico_id">Asignar ahora <span class="font-normal muted">(opcional)</span></label>
                        <select id="tecnico_id" name="tecnico_id" class="input sm:max-w-sm">
                            <option value="">Dejar en la bandeja</option>
                            @foreach (\App\Models\User::where('activo', true)->whereIn('rol', ['tecnico', 'jefe'])->orderBy('name')->get() as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}{{ $u->especialidad ? ' ('.$u->especialidad.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-xl border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5">
                    <a href="{{ route('tickets.index') }}" class="btn">Cancelar</a>
                    <button class="btn btn-primary"><i class="ti ti-check"></i>Registrar ticket</button>
                </div>
            </section>
        </form>
    </div>
</x-layout>
