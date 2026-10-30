<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PushController extends Controller
{
    public function suscribir(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required',
            'keys.auth' => 'required',
        ]);

        $request->user()->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']);

        return response()->json(['ok' => true]);
    }
}
