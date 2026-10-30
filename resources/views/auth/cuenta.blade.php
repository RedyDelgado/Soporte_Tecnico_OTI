@php $u = auth()->user(); @endphp
<x-layout titulo="Mi cuenta">
    <x-cabecera titulo="Mi cuenta" subtitulo="Tus datos y tu contraseña." />

    <div class="grid gap-4 lg:grid-cols-[1fr_2fr]">
        <div class="card p-4 text-center">
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-brand-600 text-xl font-semibold text-white">{{ collect(explode(' ', $u->name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}</span>
            <div class="mt-3 font-semibold text-neutral-900">{{ $u->name }}</div>
            <div class="text-sm muted">{{ $u->email }}</div>
            <div class="mt-3 flex justify-center gap-2">
                <span class="badge">{{ \App\Models\User::ROLES[$u->rol] }}</span>
                @if ($u->especialidad)<x-etiqueta :esp="$u->especialidad" />@endif
            </div>
        </div>

        <form method="POST" action="{{ route('cuenta.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><span class="card-title">Datos de la cuenta</span></div>
            <div class="space-y-4 p-4">
                <div>
                    <label class="label" for="name">Nombre</label>
                    <input id="name" name="name" value="{{ old('name', $u->name) }}" class="input" required>
                </div>
                <div class="border-t border-neutral-100 pt-5">
                    <div class="mb-3 text-sm font-medium text-neutral-900">Cambiar contraseña <span class="font-normal muted">(déjalo vacío para mantenerla)</span></div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label" for="password_actual">Actual</label>
                            <input id="password_actual" name="password_actual" type="password" class="input" autocomplete="current-password">
                            @error('password_actual')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="password">Nueva</label>
                            <input id="password" name="password" type="password" class="input" minlength="8" autocomplete="new-password">
                            @error('password')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="password_confirmation">Repetir nueva</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5 rounded-b-xl">
                <button class="btn btn-primary"><i class="ti ti-check"></i>Guardar cambios</button>
            </div>
        </form>
    </div>
</x-layout>
