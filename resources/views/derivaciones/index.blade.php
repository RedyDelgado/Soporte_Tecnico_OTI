<x-layout titulo="Derivaciones">
    @php $puedeAsignar = auth()->user()->puedeAsignar(); @endphp
    <x-cabecera titulo="Derivaciones" :subtitulo="$puedeAsignar ? 'Trabajo derivado a los especialistas con su número de proveído.' : 'Trabajos que te derivaron. Ciérralos indicando cómo se resolvieron.'">
        @if ($puedeAsignar)
            <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-derivar').showModal()"><i class="ti ti-plus"></i>Nueva derivación</button>
        @endif
    </x-cabecera>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-100 px-4 py-3">
            <div class="flex rounded-lg bg-neutral-100 p-0.5 text-sm">
                @foreach (['abierta' => 'Abiertas', 'cerrada' => 'Cerradas', 'todas' => 'Todas'] as $k => $txt)
                    <a href="?{{ http_build_query(['estado' => $k, 'por_pagina' => request('por_pagina')]) }}" @class([
                        'rounded-md px-3 py-1.5 font-medium transition',
                        'bg-white text-neutral-900 shadow-sm' => $estado === $k,
                        'text-neutral-500 hover:text-neutral-800' => $estado !== $k,
                    ])>{{ $txt }}</a>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="tabla min-w-[800px]">
                <thead>
                    <tr><th>Proveído</th><th>Trabajo</th>@if ($puedeAsignar)<th>Especialista</th>@endif<th>Derivado</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($derivaciones as $d)
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="font-mono font-semibold text-neutral-900">{{ $d->nro_proveido }}</span>
                                @if ($d->documento)
                                    <a href="{{ route('derivaciones.documento', $d) }}" target="_blank" class="link mt-1 flex items-center gap-1 text-xs"><i class="ti ti-file-text"></i>Ver documento</a>
                                @endif
                            </td>
                            <td class="max-w-md">
                                <p class="whitespace-pre-line text-neutral-900">{{ $d->descripcion }}</p>
                                @if ($d->estado === 'cerrada')
                                    <p class="mt-1.5 whitespace-pre-line rounded-md bg-ok-50 px-2 py-1 text-sm text-ok-700"><i class="ti ti-check"></i> {{ $d->resolucion }}</p>
                                    <p class="mt-1 text-xs muted">Cerrada por {{ $d->cerrador->name }} el {{ $d->cerrada_at->format('d/m/Y H:i') }}</p>
                                @endif
                            </td>
                            @if ($puedeAsignar)
                                <td class="whitespace-nowrap">{{ $d->especialista->name }}@if ($d->especialista->especialidad)<br><x-etiqueta :esp="$d->especialista->especialidad" class="mt-1" />@endif</td>
                            @endif
                            <td class="whitespace-nowrap muted">{{ $d->created_at->format('d/m/Y H:i') }}<br><span class="text-xs">por {{ $d->asignador->name }}</span></td>
                            <td>
                                <span @class(['badge', 'border-brand-200 bg-brand-50 text-brand-700' => $d->estado === 'abierta', 'border-ok-200 bg-ok-50 text-ok-700' => $d->estado === 'cerrada'])>{{ $d->estado === 'abierta' ? 'Abierta' : 'Cerrada' }}</span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($puedeAsignar)
                                    <button type="button" class="btn btn-sm !px-2" title="Editar {{ $d->nro_proveido }}" aria-label="Editar {{ $d->nro_proveido }}"
                                            onclick="editarDerivacion(@js(['accion' => route('derivaciones.update', $d)] + $d->only('nro_proveido', 'asignado_a', 'descripcion', 'resolucion', 'estado')))"><i class="ti ti-pencil"></i></button>
                                @endif
                                @if ($d->puedeCerrar(auth()->user()))
                                    <button type="button" class="btn btn-sm !px-2" title="Cerrar {{ $d->nro_proveido }}" aria-label="Cerrar {{ $d->nro_proveido }}"
                                            onclick="cerrarDerivacion(@js(route('derivaciones.cerrar', $d)), @js($d->nro_proveido))"><i class="ti ti-lock"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center muted">No hay derivaciones {{ $estado === 'todas' ? '' : ($estado === 'cerrada' ? 'cerradas' : 'abiertas') }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :items="$derivaciones" />
    </div>

    @if ($puedeAsignar)
        <x-modal id="modal-derivar" titulo="Nueva derivación">
            <form method="POST" action="{{ route('derivaciones.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="d-especialista">Especialista</label>
                    <select id="d-especialista" name="asignado_a" class="input" required>
                        <option value="">Elegir…</option>
                        @foreach ($especialistas as $e)
                            <option value="{{ $e->id }}" @selected(old('asignado_a') == $e->id)>{{ $e->name }} · {{ \App\Models\Categoria::ESPECIALIDADES[$e->especialidad] ?? $e->especialidad }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="d-proveido">N.º de proveído</label>
                    <input id="d-proveido" name="nro_proveido" class="input" required maxlength="50" value="{{ old('nro_proveido') }}" placeholder="PROV-012-2026">
                </div>
                <div>
                    <label class="label" for="d-descripcion">Trabajo a realizar</label>
                    <textarea id="d-descripcion" name="descripcion" rows="3" class="input" required maxlength="2000">{{ old('descripcion') }}</textarea>
                </div>
                <div>
                    <label class="label" for="d-documento">Documento escaneado <span class="font-normal muted">(opcional)</span></label>
                    <input id="d-documento" type="file" name="documento" accept="application/pdf,image/*" class="input file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-2.5 file:py-1 file:text-sm">
                    <p class="mt-1 text-xs muted">PDF o foto del proveído, hasta 10 MB. En el celular puedes tomar la foto directamente.</p>
                    @error('documento')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button class="btn btn-primary"><i class="ti ti-send"></i>Derivar</button>
                </div>
            </form>
        </x-modal>
    @endif

    <x-modal id="modal-cerrar" titulo="Cerrar derivación">
        <form method="POST" id="form-cerrar" class="space-y-4">
            @csrf
            <p class="text-sm muted">Proveído <b id="cerrar-proveido" class="text-neutral-900"></b>. Indica cómo se resolvió.</p>
            <div>
                <label class="label" for="cerrar-resolucion">Resolución</label>
                <textarea id="cerrar-resolucion" name="resolucion" rows="3" class="input" required maxlength="2000"></textarea>
            </div>
            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                <button class="btn btn-primary"><i class="ti ti-lock"></i>Cerrar derivación</button>
            </div>
        </form>
    </x-modal>

    @if ($puedeAsignar)
        <x-modal id="modal-editar-derivacion" titulo="Editar derivación">
            <form method="POST" id="form-editar" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="label" for="e-especialista">Especialista</label>
                    <select id="e-especialista" name="asignado_a" class="input" required>
                        @foreach ($especialistas as $e)
                            <option value="{{ $e->id }}">{{ $e->name }} · {{ \App\Models\Categoria::ESPECIALIDADES[$e->especialidad] ?? $e->especialidad }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="e-proveido">N.º de proveído</label>
                    <input id="e-proveido" name="nro_proveido" class="input" required maxlength="50">
                </div>
                <div>
                    <label class="label" for="e-descripcion">Trabajo a realizar</label>
                    <textarea id="e-descripcion" name="descripcion" rows="3" class="input" required maxlength="2000"></textarea>
                </div>
                <div id="e-resolucion-campo">
                    <label class="label" for="e-resolucion">Resolución</label>
                    <textarea id="e-resolucion" name="resolucion" rows="3" class="input" maxlength="2000"></textarea>
                </div>
                <div>
                    <label class="label" for="e-documento">Reemplazar documento <span class="font-normal muted">(opcional)</span></label>
                    <input id="e-documento" type="file" name="documento" accept="application/pdf,image/*" class="input file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-2.5 file:py-1 file:text-sm">
                    <p class="mt-1 text-xs muted">Déjalo vacío para conservar el documento actual.</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button class="btn btn-primary"><i class="ti ti-device-floppy"></i>Guardar cambios</button>
                </div>
            </form>
        </x-modal>
    @endif
    <script>
        window.editarDerivacion = (d) => {
            const f = document.getElementById('form-editar');
            f.action = d.accion;
            f.asignado_a.value = d.asignado_a;
            f.nro_proveido.value = d.nro_proveido;
            f.descripcion.value = d.descripcion;
            f.documento.value = '';
            const cerrada = d.estado === 'cerrada';
            document.getElementById('e-resolucion-campo').hidden = !cerrada;
            f.resolucion.disabled = !cerrada;
            f.resolucion.required = cerrada;
            f.resolucion.value = d.resolucion ?? '';
            document.getElementById('modal-editar-derivacion').showModal();
        };
        window.cerrarDerivacion = (accion, proveido) => {
            document.getElementById('form-cerrar').action = accion;
            document.getElementById('cerrar-proveido').textContent = proveido;
            document.getElementById('cerrar-resolucion').value = '';
            document.getElementById('modal-cerrar').showModal();
        };
    </script>
</x-layout>
