<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Lo que usa instalar.sh en el servidor: el seeder de producción y el comando oti:usuario. */
class DespliegueTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_produccion_el_seeder_solo_carga_datos_base_y_se_puede_repetir(): void
    {
        User::query()->delete();
        $this->app->detectEnvironment(fn () => 'production');

        $this->app->make(DatabaseSeeder::class)->run();
        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertSame(4, Sede::count());
        $this->assertTrue(Categoria::where('especialidad', 'servidores')->exists());
        $this->assertSame(1, Categoria::where('nombre', 'Por clasificar')->count());
        $this->assertSame(0, User::count());
    }

    public function test_oti_usuario_crea_la_cuenta_con_una_clave_segura(): void
    {
        $this->artisan('oti:usuario', ['email' => 'RDelgado@uniq.edu.pe', 'nombre' => 'Redy Delgado'])
            ->expectsOutputToContain('Creado: Redy Delgado <rdelgado@uniq.edu.pe> como Administrador')
            ->assertSuccessful();

        $admin = User::firstWhere('email', 'rdelgado@uniq.edu.pe');
        $this->assertSame('jefe', $admin->rol);
        $this->assertTrue($admin->activo);

        // Volver a correrlo actualiza la misma cuenta y cambia la clave.
        $this->artisan('oti:usuario', ['email' => 'rdelgado@uniq.edu.pe', 'nombre' => 'Redy Delgado', '--rol' => 'tecnico', '--especialidad' => 'redes'])
            ->expectsOutputToContain('Actualizado')->assertSuccessful();
        $this->assertSame(1, User::where('email', 'rdelgado@uniq.edu.pe')->count());
        $this->assertSame(['tecnico', 'redes'], [$admin->fresh()->rol, $admin->fresh()->especialidad]);
        $this->assertFalse(Hash::check('', $admin->fresh()->password));

        $this->artisan('oti:usuario', ['email' => 'x@uniq.edu.pe', 'nombre' => 'X', '--rol' => 'director'])->assertFailed();
        $this->artisan('oti:usuario', ['email' => 'no-es-correo', 'nombre' => 'X'])->assertFailed();
    }
}
