@props(['ticket'])
@php [$tipo, $texto] = $ticket->mensajeParaSolicitante() ?? [null, null]; @endphp
@if ($texto)
    <div {{ $attributes->class([
        'flex items-start gap-2 rounded-lg px-3 py-2 text-sm',
        'bg-warn-50 text-warn-800' => $tipo === 'ocupado',
        'bg-info-50 text-info-700' => $tipo !== 'ocupado',
    ]) }}>
        <i @class(['ti mt-0.5', 'ti-clock-pause' => $tipo === 'ocupado', 'ti-car' => $tipo === 'camino', 'ti-hourglass' => $tipo === 'espera'])></i>
        <span>{{ $texto }}</span>
    </div>
@endif
