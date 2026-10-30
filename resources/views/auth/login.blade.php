@php $url = url('/login'); @endphp
<x-layout titulo="Soporte TI">
    <div class="mx-auto flex min-h-[85vh] max-w-5xl items-center">
        <div class="grid w-full gap-6 md:grid-cols-[1.3fr_1fr]">
            {{-- Ingreso --}}
            <div class="card p-6 sm:p-8">
                <img src="/icono.svg" class="mb-4 size-11" alt="">
                <h1 class="text-2xl">Mesa de ayuda OTI</h1>
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
                    <a href="{{ route('google') }}" class="btn mt-6 w-full !py-2.5 !text-[0.95rem]">
                        <svg class="size-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                        Continuar con mi correo UNIQ
                    </a>
                    <p class="hint text-center">Solo se aceptan cuentas {{ '@'.config('oti.dominio') }}.</p>
                @else
                    <div class="mt-6 rounded-lg border border-warn-200 bg-warn-50 px-3 py-2 text-sm text-warn-800">
                        <i class="ti ti-info-circle"></i> El ingreso con Google aún no está configurado (faltan GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET).
                    </div>
                @endif

                @if (config('oti.login_password'))
                    <details class="mt-6 border-t border-neutral-100 pt-4" @if ($errors->any() || ! $googleListo) open @endif>
                        <summary class="cursor-pointer text-sm muted">Ingreso con contraseña (solo para pruebas)</summary>
                        <form method="POST" action="/login" class="mt-3 space-y-3">
                            @csrf
                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autocomplete="username" placeholder="correo" aria-label="Correo">
                            <input id="password" name="password" type="password" class="input" required autocomplete="current-password" placeholder="contraseña" aria-label="Contraseña">
                            <button class="btn w-full"><i class="ti ti-key"></i>Ingresar</button>
                        </form>
                    </details>
                @endif
            </div>

            {{-- QR para el celular --}}
            <div class="card flex flex-col items-center justify-center p-6 text-center">
                <div class="mb-1 flex items-center gap-2 font-semibold text-neutral-900"><i class="ti ti-device-mobile text-brand-600"></i>Regístralo desde tu celular</div>
                <p class="mb-4 text-sm muted">Escanea el código con la cámara y haz todo el registro desde el teléfono.</p>
                <x-qr :texto="$url" :tamano="200" class="rounded-xl border border-neutral-200 bg-white p-3" />
                <p class="mt-3 text-xs break-all muted">{{ $url }}</p>
            </div>
        </div>
    </div>
</x-layout>
