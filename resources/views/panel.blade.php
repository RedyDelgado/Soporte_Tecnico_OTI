@php
    $jefe = auth()->user()->puedeAsignar(); // jefe y mesa ven la oficina completa
    $fmt = fn ($v, $suf = '') => $v === null ? '—' : $v.$suf;
    $dur = fn ($min) => match (true) {
        $min === null => '—',
        $min < 60 => $min.' min',
        $min < 1440 => round($min / 60, 1).' h',
        default => round($min / 1440, 1).' días',
    };
@endphp
<x-layout titulo="Panel">
    <x-cabecera :titulo="$jefe ? 'Panel de la oficina' : 'Mi panel'" subtitulo="Indicadores del mes elegido.">
        <form class="flex gap-2">
            <input type="month" name="mes" value="{{ $mes->format('Y-m') }}" class="input !w-auto !py-1.5" onchange="this.form.requestSubmit()" aria-label="Mes">
            @if ($jefe)
                <select name="sede" class="input !w-auto !py-1.5" onchange="this.form.requestSubmit()" aria-label="Sede">
                    <option value="">Todas las sedes</option>
                    @foreach ($sedes as $s)
                        <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            @endif
        </form>
    </x-cabecera>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Cumplimiento del plazo', $fmt($kpi['sla'], '%'), 'ti-target', 'Resueltos dentro del SLA', $kpi['sla'] !== null && $kpi['sla'] < 80 ? 'text-danger-600' : 'text-ok-600'],
            ['Abiertos ahora', $kpi['abiertos'], 'ti-inbox', $kpi['vencidos'].' vencidos', $kpi['vencidos'] ? 'text-danger-600' : 'text-neutral-500'],
            ['Resueltos en el mes', $kpi['resueltos'], 'ti-circle-check', $fmt($kpi['remota'], '%').' remotos', 'text-neutral-500'],
            ['Tiempo de solución', $dur($kpi['solucion']), 'ti-clock', 'Respuesta: '.$dur($kpi['respuesta']), 'text-neutral-500'],
        ] as [$label, $valor, $icono, $sub, $color])
            <div class="card p-4">
                <div class="flex items-center justify-between text-sm font-medium muted">{{ $label }}<span class="grid size-8 place-items-center rounded-lg bg-brand-50"><i class="ti {{ $icono }} text-lg text-brand-600"></i></span></div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-neutral-900">{{ $valor }}</div>
                <div class="mt-1 text-xs {{ $color }}">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card p-4 lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold">Tendencia semanal</h2>
            <div class="h-64"><canvas id="tendencia" aria-label="Tickets creados y resueltos por semana"></canvas></div>
        </div>
        <div class="card p-4">
            <h2 class="mb-4 text-sm font-semibold">Abiertos por estado</h2>
            <div class="space-y-3">
                @foreach ($porEstado as $estado => $n)
                    <div class="flex items-center justify-between text-sm"><span>{{ $estado }}</span><span class="font-semibold tabular-nums">{{ $n }}</span></div>
                @endforeach
                <div class="flex items-center justify-between border-t border-neutral-100 pt-3 text-sm">
                    <span>Reabiertos en el mes</span><span class="font-semibold tabular-nums">{{ $fmt($kpi['reabiertos'], '%') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @foreach (['Pedidos por sede' => $porSede, 'Categorías más pedidas' => $porCategoria] as $titulo => $serie)
            <div class="card p-4">
                <h2 class="mb-4 text-sm font-semibold">{{ $titulo }}</h2>
                @forelse ($serie as $nombre => $n)
                    <div class="mb-3">
                        <div class="mb-1 flex justify-between text-sm"><span class="truncate">{{ $nombre }}</span><span class="font-semibold tabular-nums">{{ $n }}</span></div>
                        <div class="h-2 rounded-full bg-neutral-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ round($n * 100 / $serie->max()) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm muted">Sin pedidos este mes.</p>
                @endforelse
            </div>
        @endforeach
    </div>

    @if ($jefe)
        <div class="card mt-4 overflow-x-auto">
            <h2 class="px-5 pt-5 text-sm font-semibold">Carga del equipo</h2>
            <table class="tabla mt-3">
                <thead class="text-left text-xs muted">
                    <tr class="border-b border-neutral-100">
                        <th class="px-5 py-2 font-medium">Técnico</th><th class="px-3 py-2 text-right font-medium">Abiertos</th>
                        <th class="px-3 py-2 text-right font-medium">Resueltos</th><th class="px-3 py-2 text-right font-medium">Solución prom.</th>
                        <th class="px-5 py-2 text-right font-medium">En plazo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($equipo as $f)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $f['nombre'] }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $f['abiertos'] }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $f['resueltos'] }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $dur($f['solucion']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $fmt($f['sla'], '%') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center muted">Sin actividad este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <script type="module">
        Chart.defaults.color = '#64748b';
        Chart.defaults.borderColor = '#f1f5f9';
        new Chart(document.getElementById('tendencia'), {
            type: 'bar',
            data: {
                labels: @json($tendencia['labels']),
                datasets: [
                    { label: 'Creados', data: @json($tendencia['creados']), backgroundColor: '#b9cce3', borderRadius: 6 },
                    { label: 'Resueltos', data: @json($tendencia['resueltos']), backgroundColor: '#264f80', borderRadius: 6 },
                ],
            },
            options: { animation: false, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { position: 'bottom' } } },
        });
    </script>
</x-layout>
