@php
    $pasos = ['pendiente' => 'Recibido', 'asignado' => 'Asignado', 'en_camino' => 'En camino', 'en_atencion' => 'En atención', 'resuelto' => 'Resuelto'];
    $actual = array_search($ticket->estado, array_keys($pasos));
@endphp
<x-layout :titulo="$ticket->codigo">
    <div class="mx-auto max-w-lg">
        <div class="mb-4 flex items-center gap-2">
            <img src="/logo.webp" class="size-12" alt="Llama OTI">
            <span class="font-semibold">Oficina de Tecnologías de la Información</span>
        </div>
        @if (session('registrado'))
            <div class="card mb-4 border-ok-200 p-4" x-data="{ copiado: false }">
                <div class="mb-1 flex items-center gap-2 font-semibold text-ok-700"><i class="ti ti-circle-check"></i>Pedido registrado</div>
                <p class="mb-3 text-sm">Guarda este enlace (o toma una captura): con él verás el avance de tu pedido <b>{{ $ticket->codigo }}</b>.</p>
                <div class="flex gap-2">
                    <input readonly value="{{ route('seguimiento', $ticket->token) }}" class="input !text-xs" aria-label="Enlace de seguimiento">
                    <button type="button" class="btn !px-2.5" @click="navigator.clipboard.writeText('{{ route('seguimiento', $ticket->token) }}'); copiado = true; setTimeout(() => copiado = false, 2000)" aria-label="Copiar enlace">
                        <i class="ti" :class="copiado ? 'ti-check text-ok-600' : 'ti-copy'"></i>
                    </button>
                </div>
            </div>
        @endif
        <div class="card p-4">
            <p class="text-sm muted">Su pedido</p>
            <h1 class="font-mono text-2xl">{{ $ticket->codigo }}</h1>
            <p class="mt-2">{{ $ticket->descripcion }}</p>
            <p class="mt-1 text-sm muted">{{ $ticket->sede->nombre }} · {{ $ticket->area->nombre }} · {{ $ticket->created_at->format('d/m/Y H:i') }}</p>

            <x-mensaje-pedido :ticket="$ticket" class="mt-4" />

            @if (in_array($ticket->estado, \App\Models\Ticket::ABIERTOS) || $ticket->estado === 'resuelto')
            <ol class="mt-4 space-y-0">
                @foreach (array_values($pasos) as $i => $paso)
                    <li class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span @class(['grid size-7 place-items-center rounded-full text-sm',
                                'bg-ok-500 text-white' => $i <= $actual,
                                'bg-neutral-200 text-neutral-500' => $i > $actual])>
                                <i class="ti {{ $i <= $actual ? 'ti-check' : 'ti-point' }}"></i>
                            </span>
                            @if (! $loop->last)<span @class(['h-6 w-0.5', 'bg-ok-500' => $i < $actual, 'bg-neutral-200' => $i >= $actual])></span>@endif
                        </div>
                        <div @class(['pt-0.5 text-sm', 'font-semibold' => $i === $actual, 'muted' => $i > $actual])>
                            {{ $paso }}
                            @if ($i === $actual && $ticket->tecnico && $i > 0) <span class="font-normal muted">· {{ $ticket->tecnico->name }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
            @endif

            @if ($ticket->estado === 'resuelto')
                <div class="mt-4 rounded-xl bg-ok-50 p-4 text-sm">
                    <p class="font-medium text-ok-700 font-semibold">Solución</p>
                    <p class="mt-1 text-ok-700">{{ $ticket->solucion }}</p>
                </div>
            @endif
        </div>
        <p class="mt-4 text-center text-xs muted">Si el problema continúa, responda al mismo chat o correo indicando el código.</p>
    </div>
</x-layout>
