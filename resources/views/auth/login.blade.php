@php
    $urlUniq = url('/login');
    $urlSinCorreo = route('pedido.publico');
@endphp
<x-layout titulo="Soporte TI">
    <div class="mx-auto max-w-5xl py-6 sm:py-10">
        <div class="grid w-full gap-4 md:grid-cols-[1.3fr_1fr]">
            {{-- Ingreso con correo UNIQ --}}
            <div class="card p-6 sm:p-8">
                <img src="/logo.webp" class="mb-4 size-24" alt="Llama OTI">
                <h1 class="text-2xl">Llama OTI</h1>
                <p class="font-medium text-brand-700">Mesa de ayuda de la OTI</p>
                <p class="mt-1 muted">Universidad Nacional Intercultural de Quillabamba · Oficina de Tecnologías de la Información</p>

                <ol class="mt-6 space-y-3 text-neutral-700">
                    @foreach ([
                        ['ti-login-2', 'Ingresa con tu correo institucional @'.config('oti.dominio').'.'],
                        ['ti-message-report', 'Cuéntanos qué pasa y, si puedes, adjunta hasta 3 fotos.'],
                        ['ti-bell-ringing', 'Sigue el avance de tu pedido hasta que se resuelva.'],
                    ] as [$icono, $texto])
                        <li class="flex items-start gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50"><i class="ti {{ $icono }} text-brand-600"></i></span>
                            <span class="pt-1.5">{{ $texto }}</span>
                        </li>
                    @endforeach
                </ol>

                @error('email')
                    <div class="mt-5 flex items-center gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-danger-700"><i class="ti ti-alert-circle"></i>{{ $message }}</div>
                @enderror

                @if ($googleListo)
                    <a href="{{ route('google') }}" class="btn btn-primary mt-6 w-full !py-2.5 !text-[0.95rem]">
                        <span class="grid size-6 place-items-center rounded bg-white">
                            <svg class="size-4" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                        </span>
                        Continuar con mi correo UNIQ
                    </a>
                    <p class="hint text-center">Solo se aceptan cuentas {{ '@'.config('oti.dominio') }}.</p>
                @else
                    <div class="mt-6 rounded-lg border border-warn-200 bg-warn-50 px-3 py-2 text-sm text-warn-800">
                        <i class="ti ti-info-circle"></i> El ingreso con Google aún no está configurado (faltan GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET).
                    </div>
                @endif
            </div>

            <div class="card flex flex-col items-center justify-center p-6 text-center">
                <div class="mb-1 flex items-center gap-2 font-semibold text-neutral-900"><i class="ti ti-device-mobile text-brand-600"></i>Con tu correo UNIQ, desde el celular</div>
                <p class="mb-4 text-sm muted">Escanea con la cámara y haz todo el registro desde el teléfono.</p>
                <x-qr :texto="$urlUniq" :tamano="180" class="rounded-xl border border-neutral-200 bg-white p-3" />
                <p class="mt-3 text-xs break-all muted">{{ $urlUniq }}</p>
            </div>

            {{-- Sin correo institucional --}}
            <div class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center md:col-span-2">
                <div class="flex-1">
                    <div class="mb-1 flex items-center gap-2 font-semibold text-neutral-900"><i class="ti ti-user-question text-warn-500"></i>¿No tienes correo institucional?</div>
                    <p class="text-sm muted">Registra tu pedido con tu nombre, celular y correo personal. La mesa de ayuda lo valida y, apenas lo apruebe, entra a la cola de atención. Recibirás un enlace para seguir el avance.</p>
                    <a href="{{ $urlSinCorreo }}" class="btn mt-4"><i class="ti ti-forms"></i>Registrar mi pedido sin correo UNIQ</a>
                </div>
                <div class="flex flex-col items-center text-center">
                    <x-qr :texto="$urlSinCorreo" :tamano="140" class="rounded-xl border border-neutral-200 bg-white p-2.5" />
                    <p class="mt-2 text-xs break-all muted">{{ $urlSinCorreo }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layout>
