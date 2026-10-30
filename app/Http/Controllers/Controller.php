<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    const POR_PAGINA = [10, 20, 50, 100];

    /** Filas por página elegidas en el listado (?por_pagina=), 10 por defecto. */
    protected function porPagina(Request $request): int
    {
        $n = (int) $request->query('por_pagina');

        return in_array($n, self::POR_PAGINA, true) ? $n : 10;
    }
}
