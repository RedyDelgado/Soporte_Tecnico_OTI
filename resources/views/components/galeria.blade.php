@props(['fotos', 'titulo' => 'Fotografías'])
@if ($fotos->isNotEmpty())
    <div x-data="{ src: '' }">
        <div class="mb-2 text-xs muted">{{ $titulo }}</div>
        <div class="flex flex-wrap gap-2">
            @foreach ($fotos as $f)
                <button type="button" @click="src = '{{ $f->url() }}'; $refs.visor.showModal()" class="size-20 overflow-hidden rounded-lg border border-neutral-200 transition hover:ring-2 hover:ring-brand-400">
                    <img src="{{ $f->url() }}" class="size-full object-cover" alt="Foto {{ $loop->iteration }}" loading="lazy">
                </button>
            @endforeach
        </div>
        <dialog x-ref="visor" class="modal !max-w-3xl" @click.self="$refs.visor.close()">
            <div class="relative">
                <img :src="src" class="max-h-[80vh] w-full object-contain" alt="">
                <button type="button" onclick="this.closest('dialog').close()" class="absolute top-2 right-2 grid size-8 place-items-center rounded-full bg-white/90 shadow" aria-label="Cerrar"><i class="ti ti-x"></i></button>
            </div>
        </dialog>
    </div>
@endif
