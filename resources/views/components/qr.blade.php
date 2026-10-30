@props(['texto', 'tamano' => 200])
@php
    // SVG generado en el servidor: no necesita GD ni internet.
    $svg = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle($tamano, 1),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd,
    )))->writeString($texto);
@endphp
<div {{ $attributes }} role="img" aria-label="Código QR para abrir {{ $texto }}">{!! preg_replace('/^<\?xml.*?\?>\s*/', '', $svg) !!}</div>
