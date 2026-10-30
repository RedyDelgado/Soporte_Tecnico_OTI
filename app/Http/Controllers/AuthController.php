<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /** Página pública: ingreso con el correo UNIQ y QR para abrirla en el celular. */
    public function form()
    {
        return view('auth.login', ['googleListo' => filled(config('services.google.client_id'))]);
    }

    public function google()
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        // hd sugiere a Google mostrar solo cuentas del dominio; igual se verifica al volver.
        return Socialite::driver('google')->with(['hd' => config('oti.dominio'), 'prompt' => 'select_account'])->redirect();
    }

    public function googleCallback(Request $request)
    {
        try {
            $g = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'No se pudo completar el ingreso con Google. Intenta de nuevo.']);
        }

        $email = Str::lower($g->getEmail());
        if (! Str::endsWith($email, '@'.config('oti.dominio')) || ($g->user['hd'] ?? null) !== config('oti.dominio')) {
            return redirect()->route('login')->withErrors(['email' => 'Usa tu correo institucional @'.config('oti.dominio').'.']);
        }

        // Primera vez: se crea como "usuario" (solicita soporte). El administrador le asigna otro rol si corresponde.
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $g->getName() ?: Str::before($email, '@'),
            'password' => Str::random(40),
            'rol' => 'usuario',
        ]);
        if (! $user->activo) {
            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada. Comunícate con la OTI.']);
        }
        $user->update(['google_id' => $g->getId(), 'ultimo_ingreso' => now()]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended($user->inicio());
    }

    /** Ingreso con contraseña: solo si OTI_LOGIN_PASSWORD=true (desarrollo o mientras se configura Google). */
    public function login(Request $request)
    {
        abort_unless(config('oti.login_password'), 404);
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($cred + ['activo' => true], true)) {
            return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->user()->update(['ultimo_ingreso' => now()]);

        return redirect()->intended($request->user()->inicio());
    }

    public function cuenta()
    {
        return view('auth.cuenta');
    }

    public function actualizarCuenta(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:80',
            'password_actual' => 'required_with:password|current_password',
            'password' => 'nullable|min:8|confirmed',
        ]);

        $request->user()->update(array_filter(['name' => $data['name'], 'password' => $data['password'] ?? null]));

        return back()->with('ok', 'Tu cuenta se actualizó');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
