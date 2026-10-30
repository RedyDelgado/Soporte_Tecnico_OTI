<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\TicketFoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fotos, paginación, directorio, cuenta y las fallas corregidas en la revisión del 2026-10-30. */
class RevisionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->first();
    }

    // PNG real de 1x1 (no depende de GD para crearlo).
    private function foto(string $nombre = 'foto.png'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        return new UploadedFile($ruta, $nombre, 'image/png', null, true);
    }

    private function ticket(): Ticket
    {
        $area = Area::firstOrCreate(['sede_id' => 1, 'nombre' => 'Caja']);
        $p = Solicitante::create(['nombre' => 'Eva', 'area_id' => $area->id]);

        return Ticket::create(['sede_id' => 1, 'area_id' => $area->id, 'solicitante_id' => $p->id, 'canal' => 'llamada',
            'categoria_id' => 1, 'prioridad' => 'media', 'descripcion' => 'PC lenta', 'creado_por' => 1]);
    }

    public function test_fotos_hasta_tres_por_formulario(): void
    {
        Storage::fake('public');
        $mesa = $this->usuario('mesa@oti.local');
        $area = Area::create(['sede_id' => 1, 'nombre' => 'Caja']);
        $base = ['sede_id' => 1, 'area_id' => $area->id, 'nombre' => 'Eva', 'canal' => 'llamada', 'categoria_id' => 1, 'prioridad' => 'media', 'descripcion' => 'x'];

        $this->actingAs($mesa)->post('/tickets', $base + ['fotos' => [$this->foto(), $this->foto(), $this->foto(), $this->foto()]])
            ->assertSessionHasErrors('fotos');

        $this->actingAs($mesa)->post('/tickets', $base + ['fotos' => [$this->foto(), $this->foto()]])->assertSessionHasNoErrors();
        $fotos = TicketFoto::all();
        $this->assertCount(2, $fotos);
        $this->assertSame('pedido', $fotos[0]->etapa);
        Storage::disk('public')->assertExists($fotos[0]->ruta);

        // Al cerrar, el técnico adjunta las suyas.
        $t = Ticket::latest('id')->first();
        $luis = $this->usuario('luis@oti.local');
        $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $luis->id]);
        $this->actingAs($luis)->post("/tickets/{$t->id}/resolver", ['categoria_id' => 1, 'modalidad' => 'presencial',
            'diagnostico' => 'Disco lleno de temporales', 'solucion' => 'Se limpió el disco y se reinició', 'fotos' => [$this->foto()]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $t->fotos()->where('etapa', 'cierre')->count());
    }

    public function test_paginacion_acepta_solo_10_20_50_100(): void
    {
        $jefe = $this->usuario('jefe@oti.local');
        foreach (range(1, 12) as $_) {
            $this->ticket();
        }
        $this->actingAs($jefe)->get('/bandeja?f=all')->assertSee('1–10')->assertSee('de <b class="font-medium text-neutral-700">12</b>', false);
        $this->actingAs($jefe)->get('/bandeja?f=all&por_pagina=20')->assertSee('1–12');
        $this->actingAs($jefe)->get('/bandeja?f=all&por_pagina=7')->assertSee('1–10');
        foreach (['/usuarios', '/categorias', '/directorio', '/soluciones?q=x', '/tickets/mios'] as $url) {
            $this->actingAs($jefe)->get($url.(str_contains($url, '?') ? '&' : '?').'por_pagina=50')->assertOk();
        }
    }

    public function test_directorio_registra_personas_y_areas(): void
    {
        $mesa = $this->usuario('mesa@oti.local');
        $this->actingAs($mesa)->post('/areas', ['sede_id' => 3, 'nombre' => 'Logística'])->assertRedirect();
        $area = Area::where('nombre', 'Logística')->first();

        $this->actingAs($mesa)->post('/directorio/personas', ['nombre' => 'Carlos Puma', 'telefono' => '984777888', 'area_id' => $area->id])->assertRedirect();
        $this->actingAs($mesa)->post('/directorio/personas', ['nombre' => 'Otro', 'telefono' => '984777888', 'area_id' => $area->id])
            ->assertSessionHasErrors('telefono');
        $this->assertSame(1, Solicitante::where('telefono', '984777888')->count());

        $this->actingAs($mesa)->get('/directorio?q=Puma')->assertSee('Carlos Puma');
        $this->actingAs($this->usuario('luis@oti.local'))->get('/directorio')->assertForbidden();
    }

    public function test_mi_cuenta_exige_contrasena_actual(): void
    {
        $luis = $this->usuario('luis@oti.local');
        $this->actingAs($luis)->put('/cuenta', ['name' => 'Luis', 'password' => 'nueva12345', 'password_confirmation' => 'nueva12345'])
            ->assertSessionHasErrors('password_actual');
        $this->actingAs($luis)->put('/cuenta', ['name' => 'Luis R.', 'password_actual' => 'oti12345', 'password' => 'nueva12345', 'password_confirmation' => 'nueva12345'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Luis R.', $luis->fresh()->name);
    }

    public function test_fallas_corregidas(): void
    {
        $mesa = $this->usuario('mesa@oti.local');
        $jefe = $this->usuario('jefe@oti.local');
        $luis = $this->usuario('luis@oti.local');
        $t = $this->ticket();

        // No se puede asignar a la mesa ni a un inactivo.
        $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $mesa->id])->assertSessionHasErrors('tecnico_id');

        // El estado solo avanza.
        $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $luis->id]);
        $this->actingAs($luis)->post("/tickets/{$t->id}/estado", ['estado' => 'en_atencion'])->assertSessionHasNoErrors();
        $this->actingAs($luis)->post("/tickets/{$t->id}/estado", ['estado' => 'en_camino'])->assertSessionHasErrors('estado');

        // Un técnico no abre el formulario de registro.
        $this->actingAs($luis)->get('/tickets/nuevo')->assertForbidden();

        // La mesa ve el panel de toda la oficina.
        $this->actingAs($mesa)->get('/panel')->assertSee('Panel de la oficina')->assertSee('Carga del equipo');

        // El jefe no puede quitarse su rol ni desactivarse.
        $this->actingAs($jefe)->put("/usuarios/{$jefe->id}", ['name' => 'Jefe OTI', 'email' => $jefe->email, 'rol' => 'tecnico']);
        $this->assertSame('jefe', $jefe->fresh()->rol);
        $this->assertTrue($jefe->fresh()->activo);

        // Un usuario desactivado pierde la sesión en su siguiente clic.
        $luis->update(['activo' => false]);
        $this->actingAs($luis)->get('/tickets/mios')->assertRedirect('/login');
        $this->assertGuest();
    }
}
