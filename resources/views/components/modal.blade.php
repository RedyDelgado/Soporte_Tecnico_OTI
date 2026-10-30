@props(['id' => null, 'titulo'])
{{-- Modal con <dialog> nativo. Abrir con document.getElementById(id).showModal() o x-ref + $refs.x.showModal(). --}}
<dialog @if ($id) id="{{ $id }}" @endif {{ $attributes->class('modal') }}>
    <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-2.5">
        <h2 class="font-semibold">{{ $titulo }}</h2>
        <button type="button" onclick="this.closest('dialog').close()" class="rounded-md p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700" aria-label="Cerrar"><i class="ti ti-x text-lg"></i></button>
    </div>
    <div class="p-4">{{ $slot }}</div>
</dialog>
