<x-layout titulo="Acceso de emergencia">
    <div class="mx-auto max-w-sm py-16">
        <div class="card p-6">
            <div class="mb-1 flex items-center gap-2 font-semibold text-neutral-900"><i class="ti ti-key text-brand-600"></i>Acceso con contraseña</div>
            <p class="mb-4 text-sm muted">Solo para el personal de la OTI cuando el ingreso con Google no está disponible.</p>
            @error('email')<div class="mb-3 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700">{{ $message }}</div>@enderror
            <form method="POST" action="/login" class="space-y-3">
                @csrf
                <input name="email" type="email" value="{{ old('email') }}" class="input" required autocomplete="username" placeholder="correo" aria-label="Correo">
                <input name="password" type="password" class="input" required autocomplete="current-password" placeholder="contraseña" aria-label="Contraseña">
                <button class="btn btn-primary w-full"><i class="ti ti-login-2"></i>Ingresar</button>
            </form>
            <a href="{{ route('login') }}" class="link mt-4 block text-center text-sm">Volver al ingreso con Google</a>
        </div>
    </div>
</x-layout>
