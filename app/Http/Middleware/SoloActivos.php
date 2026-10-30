<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Cierra la sesión de un usuario que fue desactivado mientras estaba conectado. */
class SoloActivos
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->activo) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada.']);
        }

        return $next($request);
    }
}
