{{-- Aviso al usuario sobre la disponibilidad de la OTI antes de pedir soporte. --}}
@php $d = \App\Models\Ticket::disponibilidad(); @endphp
@if ($d['todos_ocupados'])
    <div {{ $attributes->class('flex items-start gap-3 rounded-xl border border-warn-200 bg-warn-50 px-4 py-3 text-warn-800') }}>
        <i class="ti ti-clock-pause mt-0.5 text-xl"></i>
        <div>
            <div class="font-semibold">En este momento nuestros técnicos están ocupados</div>
            <div class="text-sm">Están atendiendo otros pedidos. Registra el tuyo: apenas se libere uno, te atenderá cuanto antes.
                @if ($d['en_espera'])Hay {{ $d['en_espera'] }} {{ $d['en_espera'] === 1 ? 'pedido' : 'pedidos' }} en espera.@endif</div>
        </div>
    </div>
@elseif ($d['en_espera'])
    <div {{ $attributes->class('flex items-center gap-3 rounded-xl border border-info-100 bg-info-50 px-4 py-2.5 text-sm text-info-700') }}>
        <i class="ti ti-info-circle text-lg"></i>Hay {{ $d['en_espera'] }} {{ $d['en_espera'] === 1 ? 'pedido' : 'pedidos' }} en espera; se atienden en orden y por prioridad.
    </div>
@endif
