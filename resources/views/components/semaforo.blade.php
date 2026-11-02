@props(['ticket'])
@php
    [$color, $texto] = [
        'verde' => ['bg-ok-500', 'A tiempo'],
        'ambar' => ['bg-warn-400', 'Por vencer'],
        'rojo' => ['bg-danger-500', 'Vencido'],
        'cumplido' => ['bg-ok-500', 'Dentro del plazo'],
        'fuera' => ['bg-danger-500', 'Fuera del plazo'],
        'neutro' => ['bg-neutral-300', 'Rechazado'],
    ][$ticket->semaforo()];
@endphp
<span {{ $attributes->class("dot !size-2.5 ring-4 ring-white $color") }} title="{{ $texto }} · vence {{ $ticket->vence_at->diffForHumans() }}" aria-label="{{ $texto }}"></span>
