@props(['esp' => null, 'estado' => null])
@php
    $estilos = [
        'soporte' => ['bg-soporte', ''], 'redes' => ['bg-redes', ''], 'servidores' => ['bg-servidores', ''], 'web' => ['bg-web', ''],
        'pendiente' => ['bg-neutral-400', ''],
        'asignado' => ['bg-brand-500', 'border-brand-200 bg-brand-50 text-brand-700'],
        'en_camino' => ['bg-info-500', 'border-info-100 bg-info-50 text-info-700'],
        'en_atencion' => ['bg-info-600', 'border-info-100 bg-info-50 text-info-700'],
        'resuelto' => ['bg-ok-500', 'border-ok-200 bg-ok-50 text-ok-700'],
    ];
    $clave = $esp ?? $estado;
    [$punto, $caja] = $estilos[$clave];
    $texto = $esp ? \App\Models\Categoria::ESPECIALIDADES[$esp] : \App\Models\Ticket::ESTADOS[$estado];
@endphp
<span {{ $attributes->class(['badge', $caja]) }}><span class="dot !size-1.5 {{ $punto }}"></span>{{ $texto }}</span>
