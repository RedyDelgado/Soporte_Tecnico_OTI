<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('oti:usuario {email} {nombre} {--rol=jefe : usuario, mesa, tecnico o jefe} {--especialidad= : soporte, redes, servidores o web}')]
#[Description('Crea o actualiza una cuenta del personal con una contraseña nueva y segura (la muestra una sola vez)')]
class CrearUsuario extends Command
{
    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $rol = $this->option('rol');
        $especialidad = $this->option('especialidad') ?: null;

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Correo no válido: $email");

            return self::FAILURE;
        }
        if (! array_key_exists($rol, User::ROLES)) {
            $this->error('Rol no válido. Usa: '.implode(', ', array_keys(User::ROLES)));

            return self::FAILURE;
        }
        if ($especialidad && ! array_key_exists($especialidad, Categoria::ESPECIALIDADES)) {
            $this->error('Especialidad no válida. Usa: '.implode(', ', array_keys(Categoria::ESPECIALIDADES)));

            return self::FAILURE;
        }

        $clave = Str::password(14, symbols: false);
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $this->argument('nombre'), 'rol' => $rol, 'especialidad' => $especialidad, 'activo' => true, 'password' => $clave,
        ]);

        $this->info(($user->wasRecentlyCreated ? 'Creado' : 'Actualizado').": {$user->name} <{$email}> como ".User::ROLES[$rol]);
        $this->line("CLAVE={$clave}");

        return self::SUCCESS;
    }
}
