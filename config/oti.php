<?php

return [
    // Solo se aceptan cuentas de este dominio (Google Workspace de la UNIQ).
    'dominio' => env('OTI_DOMINIO', 'uniq.edu.pe'),

    // Ingreso con contraseña: solo para desarrollo o mientras no esté configurado Google. En producción: false.
    'login_password' => env('OTI_LOGIN_PASSWORD', true),
];
