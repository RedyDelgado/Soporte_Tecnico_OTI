@php
    $u = auth()->user();
    $t = $ticket;
    $responsable = $t->estado !== 'resuelto' && $t->tecnico_id && ($t->participa($u) || $u->esJefe());
    $volver = url()->previous() === url()->current() ? route('tickets.index') : url()->previous();
@endphp
<x-layout :titulo="$t->codigo">
    <a href="{{ $volver }}" class="mb-4 inline-flex items-center gap-1 text-sm muted hover:text-neutral-900"><i class="ti ti-arrow-left"></i>Volver</a>

    @if (session('creado'))
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-ok-200 bg-ok-50 p-4">
            <div class="grid size-10 place-items-center rounded-full bg-ok-100"><i class="ti ti-circle-check text-xl text-ok-600"></i></div>
            <div class="flex-1">
                <p class="font-semibold text-ok-700">Ticket {{ $t->codigo }} registrado</p>
                <p class="text-sm text-ok-700/80">Avísale al solicitante su código y el link de seguimiento.</p>
            </div>
            @if ($t->linkWhatsapp())
                <a href="{{ $t->linkWhatsapp() }}" target="_blank" rel="noopener" class="btn !border-ok-600 !bg-ok-600 !text-white hover:!bg-ok-700"><i class="ti ti-brand-whatsapp"></i>Responder por WhatsApp</a>
            @endif
            <a href="{{ route('tickets.create') }}" class="btn">Registrar otro</a>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_340px]">
        <div class="space-y-4">
            <div class="card">
                <div class="flex flex-wrap items-center gap-3 border-b border-neutral-100 px-4 py-4">
                    <x-semaforo :ticket="$t" />
                    <span class="font-mono text-lg font-semibold text-neutral-900">{{ $t->codigo }}</span>
                    <x-etiqueta :estado="$t->estado" />
                    <x-etiqueta :esp="$t->categoria->especialidad" />
                    <span class="ml-auto text-sm muted">
                        {{ $t->estado === 'resuelto' ? 'Resuelto '.$t->resuelto_at->diffForHumans() : 'Vence '.$t->vence_at->diffForHumans() }}
                    </span>
                </div>
                <div class="p-4">
                    <p class="text-base leading-relaxed text-neutral-900">{{ $t->descripcion }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                        @foreach ([
                            ['Sede', $t->sede->nombre, 'ti-map-pin'],
                            ['Área', $t->area->nombre, 'ti-building'],
                            ['Solicitante', $t->solicitante->nombre, 'ti-user'],
                            ['Celular', $t->solicitante->telefono ?: '—', 'ti-phone'],
                            ['Canal', \App\Models\Ticket::CANALES[$t->canal], 'ti-message'],
                            ['Categoría', $t->categoria->nombre.' · '.ucfirst($t->prioridad), 'ti-tag'],
                        ] as [$k, $v, $i])
                            <div>
                                <dt class="flex items-center gap-1 text-xs muted"><i class="ti {{ $i }}"></i>{{ $k }}</dt>
                                <dd class="mt-0.5 font-medium text-neutral-900">{{ $v }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($t->solicitante->area_id !== $t->area_id)
                        <p class="mt-4 flex items-center gap-1.5 text-xs text-warn-700"><i class="ti ti-arrows-exchange"></i>{{ $t->solicitante->nombre }} hoy está en {{ $t->solicitante->area->nombre }}; este pedido se hizo desde {{ $t->area->nombre }}.</p>
                    @endif
                    <div class="mt-4"><x-galeria :fotos="$t->fotos->where('etapa', 'pedido')" titulo="Fotos del problema" /></div>
                </div>
                <div class="flex flex-wrap gap-2 rounded-b-xl border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5">
                    @if ($responsable && $t->estado === 'asignado')
                        <form method="POST" action="{{ route('tickets.estado', $t) }}">@csrf<input type="hidden" name="estado" value="en_camino">
                            <button class="btn"><i class="ti ti-car"></i>Voy en camino</button></form>
                    @endif
                    @if ($responsable && in_array($t->estado, ['asignado', 'en_camino']))
                        <form method="POST" action="{{ route('tickets.estado', $t) }}">@csrf<input type="hidden" name="estado" value="en_atencion">
                            <button class="btn btn-primary"><i class="ti ti-tool"></i>Empecé la atención</button></form>
                    @endif
                    @if ($t->tecnico_id === $u->id && in_array($t->estado, \App\Models\Ticket::ABIERTOS))
                        <button type="button" class="btn" onclick="document.getElementById('modal-devolver').showModal()"><i class="ti ti-arrow-back-up"></i>Devolver a la cola</button>
                    @endif
                    @if ($t->solicitante->telefono)
                        <a href="tel:{{ $t->solicitante->telefono }}" class="btn"><i class="ti ti-phone"></i>Llamar</a>
                        <a href="{{ $t->linkWhatsapp() }}" target="_blank" rel="noopener" class="btn"><i class="ti ti-brand-whatsapp"></i>WhatsApp</a>
                    @endif
                </div>
            </div>

            @if ($t->estado === 'resuelto')
                <div class="card">
                    <div class="card-header"><i class="ti ti-clipboard-check text-lg text-ok-600"></i><span class="card-title">Informe de atención</span></div>
                    <div class="space-y-4 p-4">
                        <div><div class="text-xs muted">Problema encontrado</div><p class="mt-1 whitespace-pre-line text-neutral-900">{{ $t->diagnostico }}</p></div>
                        <div><div class="text-xs muted">Solución aplicada</div><p class="mt-1 whitespace-pre-line text-neutral-900">{{ $t->solucion }}</p></div>
                        <div class="flex gap-8">
                            <div><div class="text-xs muted">Modalidad</div><p class="mt-1 font-medium capitalize">{{ $t->modalidad }}</p></div>
                            <div><div class="text-xs muted">Atendió</div><p class="mt-1 font-medium">{{ $t->tecnico?->name }}</p></div>
                        </div>
                        <x-galeria :fotos="$t->fotos->where('etapa', 'cierre')" titulo="Fotos de la atención" />
                    </div>
                </div>
            @endif

            @if ($responsable)
                <form method="POST" action="{{ route('tickets.resolver', $t) }}" enctype="multipart/form-data" class="card" id="resolver"
                      data-confirmar="¿Cerrar {{ $t->codigo }} como resuelto? Se avisará a la mesa de ayuda." data-confirmar-boton="Sí, cerrar ticket">
                    @csrf
                    <div class="card-header"><i class="ti ti-checklist text-lg text-brand-600"></i><span class="card-title">Cerrar atención</span></div>
                    <div class="space-y-4 p-4">
                        @include('tickets._categoria', ['actual' => $t->categoria_id])
                        <div>
                            <label class="label" for="diagnostico">Problema encontrado</label>
                            <textarea id="diagnostico" name="diagnostico" rows="2" class="input" required minlength="10" placeholder="El cartucho estaba mal instalado y el driver desactualizado">{{ old('diagnostico') }}</textarea>
                            @error('diagnostico')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="solucion">Cómo se solucionó</label>
                            <textarea id="solucion" name="solucion" rows="2" class="input" required minlength="10" placeholder="Se reinstaló el cartucho y se actualizó el driver a la versión 3.2">{{ old('solucion') }}</textarea>
                            @error('solucion')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <fieldset>
                            <legend class="label">Modalidad</legend>
                            <div class="flex max-w-sm gap-2">
                                @foreach (['remota' => 'ti-device-desktop-share', 'presencial' => 'ti-walk'] as $m => $i)
                                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 font-medium capitalize text-neutral-600 transition has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:text-brand-700">
                                        <input type="radio" name="modalidad" value="{{ $m }}" class="sr-only" required @checked(old('modalidad', 'presencial') === $m)><i class="ti {{ $i }}"></i>{{ $m }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-fotos etiqueta="Fotos de la atención" />
                        @error('fotos.*')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end rounded-b-xl border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5">
                        <button class="btn btn-primary"><i class="ti ti-circle-check"></i>Marcar como resuelto</button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="space-y-4">
            @if ($t->estado === 'por_validar' && $u->puedeAsignar())
                <div class="card border-warn-200 p-4">
                    <div class="mb-1 flex items-center gap-2"><i class="ti ti-user-question text-warn-500"></i><span class="card-title">Pedido sin correo UNIQ</span></div>
                    <p class="mb-3 text-sm muted">Llegó por el formulario público. Verifica que la persona sea de la UNIQ (por ejemplo, llamando a su celular) antes de aprobarlo.</p>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('tickets.validar', $t) }}" class="flex-1" data-confirmar="¿Validar el pedido {{ $t->codigo }}? Pasará a la cola por asignar." data-confirmar-boton="Validar">
                            @csrf
                            <button class="btn w-full !border-ok-600 !bg-ok-600 !text-white hover:!bg-ok-700"><i class="ti ti-check"></i>Validar</button>
                        </form>
                        <button type="button" class="btn flex-1" onclick="rechazarPedido('{{ route('tickets.rechazar', $t) }}', '{{ $t->codigo }}')"><i class="ti ti-x"></i>Rechazar</button>
                    </div>
                </div>
            @endif
            @if ($t->estado === 'rechazado')
                <div class="card border-danger-200 p-4">
                    <div class="mb-1 flex items-center gap-2"><i class="ti ti-ban text-danger-500"></i><span class="card-title">Pedido rechazado</span></div>
                    <p class="text-sm">{{ $t->motivo_rechazo }}</p>
                </div>
            @endif
            @if ($u->puedeAsignar())
                <div class="card p-4">
                    <div class="mb-1 flex items-center gap-2">
                        <span class="card-title">Datos del ticket</span>
                        @if ($t->categoria->nombre === 'Por clasificar')<span class="badge border-warn-200 bg-warn-50 text-warn-700">Por clasificar</span>@endif
                    </div>
                    <p class="mb-3 text-sm muted">Corrige sede, área, canal, categoría, prioridad o descripción{{ $t->estado === 'resuelto' ? ', y el informe de atención' : '' }}.</p>
                    <button type="button" class="btn w-full" onclick="document.getElementById('modal-editar').showModal()"><i class="ti ti-pencil"></i>Editar ticket</button>
                </div>
                @php($sedesJs = $sedes->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'areas' => $s->areas->map->only('id', 'nombre')->values()]))
                <x-modal id="modal-editar" titulo="Editar {{ $t->codigo }}">
                    <form method="POST" action="{{ route('tickets.update', $t) }}" class="space-y-4"
                          x-data="{ sedes: @js($sedesJs), sede_id: '{{ old('sede_id', $t->sede_id) }}', area_id: '{{ old('area_id', $t->area_id) }}' }">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="e-sede">Sede</label>
                                <select id="e-sede" name="sede_id" x-model="sede_id" @change="area_id = ''" class="input" required>
                                    @foreach ($sedes as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="e-area">Área u oficina</label>
                                <select id="e-area" name="area_id" x-model="area_id" class="input" required>
                                    <option value="">Elegir área</option>
                                    <template x-for="a in sedes.find(s => s.id == sede_id)?.areas ?? []" :key="a.id">
                                        <option :value="a.id" x-text="a.nombre" :selected="a.id == area_id"></option>
                                    </template>
                                </select>
                                @error('area_id')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label" for="e-canal">Canal</label>
                                <select id="e-canal" name="canal" class="input" required>
                                    @foreach (\App\Models\Ticket::CANALES as $k => $v)<option value="{{ $k }}" @selected(old('canal', $t->canal) === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="e-prioridad">Prioridad</label>
                                <select id="e-prioridad" name="prioridad" class="input" required>
                                    @foreach (['alta' => 'Alta · 4 h', 'media' => 'Media · 24 h', 'baja' => 'Baja · 72 h'] as $k => $v)<option value="{{ $k }}" @selected(old('prioridad', $t->prioridad) === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label" for="e-categoria">Categoría</label>
                            <select id="e-categoria" name="categoria_id" class="input" required>
                                @foreach ($categorias->groupBy('especialidad') as $esp => $grupo)
                                    <optgroup label="{{ \App\Models\Categoria::ESPECIALIDADES[$esp] }}">
                                        @foreach ($grupo as $c)<option value="{{ $c->id }}" @selected(old('categoria_id', $t->categoria_id) == $c->id)>{{ $c->nombre }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="e-descripcion">Descripción</label>
                            <textarea id="e-descripcion" name="descripcion" rows="2" class="input" required maxlength="2000">{{ old('descripcion', $t->descripcion) }}</textarea>
                            @error('descripcion')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        @if ($t->estado === 'resuelto')
                            <div>
                                <label class="label" for="e-diagnostico">Problema encontrado</label>
                                <textarea id="e-diagnostico" name="diagnostico" rows="2" class="input" required minlength="10" maxlength="2000">{{ old('diagnostico', $t->diagnostico) }}</textarea>
                                @error('diagnostico')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label" for="e-solucion">Cómo se solucionó</label>
                                <textarea id="e-solucion" name="solucion" rows="2" class="input" required minlength="10" maxlength="2000">{{ old('solucion', $t->solucion) }}</textarea>
                                @error('solucion')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label" for="e-modalidad">Modalidad</label>
                                <select id="e-modalidad" name="modalidad" class="input" required>
                                    @foreach (['presencial' => 'Presencial', 'remota' => 'Remota'] as $k => $v)<option value="{{ $k }}" @selected(old('modalidad', $t->modalidad) === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        <p class="text-xs muted">Si cambias la prioridad, el plazo se recalcula desde que se registró el pedido. El cambio queda en la bitácora.</p>
                        <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                            <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                            <button class="btn btn-primary"><i class="ti ti-device-floppy"></i>Guardar cambios</button>
                        </div>
                    </form>
                </x-modal>
                @if ($errors->hasAny(['sede_id', 'area_id', 'canal', 'prioridad', 'descripcion']) || ($t->estado === 'resuelto' && $errors->hasAny(['diagnostico', 'solucion'])))
                    <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('modal-editar').showModal())</script>
                @endif
            @endif
            @if ($u->puedeAsignar() && in_array($t->estado, \App\Models\Ticket::ABIERTOS))
                <form method="POST" action="{{ route('tickets.asignar', $t) }}" class="card p-4"
                      data-confirmar="¿{{ $t->tecnico ? 'Reasignar' : 'Asignar' }} {{ $t->codigo }} a {opcion}?" data-confirmar-boton="{{ $t->tecnico ? 'Reasignar' : 'Asignar' }}" data-resetear>
                    @csrf
                    <label class="card-title mb-3 block" for="tecnico_id">{{ $t->tecnico ? 'Reasignar' : 'Asignar' }}</label>
                    <select id="tecnico_id" name="tecnico_id" class="input" onchange="this.form.requestSubmit()">
                        <option value="">Elegir técnico…</option>
                        @foreach ($tecnicos->sortBy(fn ($x) => $x->especialidad === $t->categoria->especialidad ? 0 : 1) as $x)
                            <option value="{{ $x->id }}" @selected($t->tecnico_id === $x->id)>{{ $x->name }} · {{ $x->abiertos }} en cola{{ $x->ocupado ? ' · atendiendo ahora' : '' }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if ($t->tecnico)
                <div class="card p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="card-title">Equipo que atiende</span>
                        @if ($u->puedeAsignar() && $t->estado !== 'resuelto')
                            <button type="button" class="link ml-auto text-xs" onclick="document.getElementById('modal-apoyos').showModal()"><i class="ti ti-users-plus"></i> Agregar apoyo</button>
                        @endif
                    </div>
                    <ul class="space-y-2">
                        @foreach (collect([$t->tecnico])->merge($t->apoyos) as $p)
                            <li class="flex items-center gap-2.5">
                                <span class="grid size-7 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ mb_substr($p->name, 0, 1) }}</span>
                                <span class="flex-1">{{ $p->name }}</span>
                                <span @class(['badge', 'border-brand-200 bg-brand-50 text-brand-700' => $loop->first])>{{ $loop->first ? 'Responsable' : 'Apoyo' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($u->puedeAsignar() && $t->estado !== 'resuelto')
                    <x-modal id="modal-apoyos" titulo="Apoyo para {{ $t->codigo }}">
                        <form method="POST" action="{{ route('tickets.apoyos', $t) }}" class="space-y-4">
                            @csrf
                            <p class="text-sm muted">Quienes marques irán junto con <b class="text-neutral-900">{{ $t->tecnico->name }}</b>. Verán el ticket en su cola y podrán avanzarlo o cerrarlo.</p>
                            <div class="divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                                @foreach ($tecnicos->where('id', '!=', $t->tecnico_id) as $x)
                                    <label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-neutral-50">
                                        <input type="checkbox" name="apoyos[]" value="{{ $x->id }}" @checked($t->apoyos->contains($x)) class="size-4 accent-brand-600">
                                        <span class="flex-1">{{ $x->name }} @if ($x->especialidad)<span class="text-xs muted">· {{ \App\Models\Categoria::ESPECIALIDADES[$x->especialidad] }}</span>@endif</span>
                                        <span class="text-xs muted">{{ $x->abiertos }} en cola</span>
                                        @if ($x->ocupado)<span class="badge border-warn-200 bg-warn-50 text-warn-700">Atendiendo ahora</span>@endif
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                                <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                                <button class="btn btn-primary"><i class="ti ti-check"></i>Guardar equipo</button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endif

            @if ($u->puedeAsignar() && $t->estado === 'resuelto')
                <div class="card p-4">
                    <div class="card-title mb-1">¿Volvió a fallar?</div>
                    <p class="mb-4 text-sm muted">Reabre el ticket y vuelve al técnico que lo atendió.</p>
                    <button type="button" class="btn w-full" onclick="document.getElementById('modal-reabrir').showModal()"><i class="ti ti-refresh"></i>Reabrir ticket</button>
                </div>
                <x-modal id="modal-reabrir" titulo="Reabrir {{ $t->codigo }}">
                    <form method="POST" action="{{ route('tickets.reabrir', $t) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="label" for="motivo">Motivo</label>
                            <textarea id="motivo" name="motivo" rows="2" class="input" required maxlength="255" placeholder="El usuario indica que la impresora volvió a fallar"></textarea>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                            <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                            <button class="btn btn-primary"><i class="ti ti-refresh"></i>Reabrir</button>
                        </div>
                    </form>
                </x-modal>
            @endif

            <div class="card p-4" x-data="{ copiado: false }">
                <div class="card-title mb-1">Seguimiento del solicitante</div>
                <p class="mb-3 text-xs muted">Link público, sin necesidad de cuenta.</p>
                <div class="flex gap-2">
                    <input readonly value="{{ route('seguimiento', $t->token) }}" class="input !text-xs" aria-label="Link de seguimiento">
                    <button type="button" class="btn !px-2.5" @click="navigator.clipboard.writeText('{{ route('seguimiento', $t->token) }}'); copiado = true; setTimeout(() => copiado = false, 2000)" aria-label="Copiar link">
                        <i class="ti" :class="copiado ? 'ti-check text-ok-600' : 'ti-copy'"></i>
                    </button>
                </div>
            </div>

            <div class="card p-4">
                <div class="card-title mb-4">Bitácora</div>
                <ol class="relative space-y-4 border-l-2 border-neutral-100 pl-5">
                    @foreach ($t->eventos as $e)
                        <li class="relative">
                            <span @class(['absolute top-1 -left-[27px] size-3 rounded-full border-2 border-white', 'bg-brand-500' => $loop->first, 'bg-neutral-300' => ! $loop->first])></span>
                            <div class="font-medium text-neutral-900">{{ \App\Models\Ticket::ESTADOS[$e->estado] }}</div>
                            @if ($e->nota)<div class="text-sm muted">{{ $e->nota }}</div>@endif
                            <div class="mt-0.5 text-xs text-neutral-400">{{ $e->user?->name }} · {{ $e->created_at->format('d/m H:i') }}</div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>
    @includeWhen($t->estado === 'por_validar' && $u->puedeAsignar(), 'tickets._rechazar')
    @if ($t->tecnico_id === $u->id && in_array($t->estado, \App\Models\Ticket::ABIERTOS))
        <x-modal id="modal-devolver" titulo="Devolver a la cola">
            <form method="POST" action="{{ route('tickets.devolver', $t) }}" class="space-y-4">
                @csrf
                <p class="text-sm muted"><b class="text-neutral-900">{{ $t->codigo }}</b> vuelve a «Por asignar» y la mesa de ayuda recibe un aviso para pasarlo a otro técnico.</p>
                <div>
                    <label class="label" for="devolver-motivo">Motivo</label>
                    <textarea id="devolver-motivo" name="motivo" rows="2" class="input" required maxlength="255" placeholder="Estoy en otra sede / requiere a alguien de redes / no tengo el repuesto"></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button class="btn btn-primary"><i class="ti ti-arrow-back-up"></i>Devolver</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-layout>
