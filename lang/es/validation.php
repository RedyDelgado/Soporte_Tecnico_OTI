<?php

// Solo las reglas que usa el sistema; el resto cae al inglés.
return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'in' => 'El :attribute seleccionado no es válido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'unique' => 'Ya existe un registro con ese :attribute.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'boolean' => 'El campo :attribute debe ser sí o no.',
    'array' => 'El campo :attribute no es válido.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'required_with' => 'El campo :attribute es obligatorio para cambiar la contraseña.',
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.', 'file' => 'El archivo :attribute no debe superar :max KB.', 'array' => 'Puedes adjuntar como máximo :max :attribute.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],

    'attributes' => [
        'sede_id' => 'sede', 'categoria_id' => 'categoría', 'tecnico_id' => 'técnico', 'descripcion' => 'descripción',
        'diagnostico' => 'problema encontrado', 'solucion' => 'solución', 'password' => 'contraseña', 'name' => 'nombre',
        'email' => 'correo', 'telefono' => 'celular', 'password_actual' => 'contraseña actual', 'fotos' => 'fotos', 'fotos.*' => 'foto',
        'area_id' => 'área', 'nombre' => 'nombre',
    ],
];
