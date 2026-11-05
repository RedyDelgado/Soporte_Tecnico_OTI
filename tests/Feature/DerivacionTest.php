<?php

namespace Tests\Feature;

use App\Models\Derivacion;
use App\Models\User;
use App\Notifications\AvisoDerivacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DerivacionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->first();
    }

    public function test_jefe_deriva_y_el_especialista_cierra_con_resolucion(): void
    {
        Notification::fake();
        $jefe = $this->usuario('jefe@oti.local');
        $luis = $this->usuario('luis@oti.local');

        $this->actingAs($jefe)->post('/derivaciones', ['asignado_a' => $luis->id, 'nro_proveido' => 'PROV-012-2026', 'descripcion' => 'Revisar cableado del auditorio'])
            ->assertSessionHasNoErrors();
        $derivacion = Derivacion::firstOrFail();
        $this->assertSame('abierta', $derivacion->estado);
        $this->assertSame($jefe->id, $derivacion->asignado_por);
        Notification::assertSentTo($luis, AvisoDerivacion::class);

        $this->actingAs($luis)->get('/derivaciones')->assertOk()->assertSee('PROV-012-2026');

        $this->actingAs($luis)->post("/derivaciones/{$derivacion->id}/cerrar", ['resolucion' => ''])->assertSessionHasErrors('resolucion');

        $this->actingAs($luis)->post("/derivaciones/{$derivacion->id}/cerrar", ['resolucion' => 'Se cambió el switch'])->assertSessionHasNoErrors();
        $derivacion->refresh();
        $this->assertSame('cerrada', $derivacion->estado);
        $this->assertSame('Se cambió el switch', $derivacion->resolucion);
        $this->assertSame($luis->id, $derivacion->cerrada_por);
        $this->assertNotNull($derivacion->cerrada_at);
    }

    public function test_otro_tecnico_no_puede_cerrar_ni_ver_ni_derivar(): void
    {
        $derivacion = Derivacion::factory()->create(['asignado_a' => $this->usuario('luis@oti.local')->id, 'nro_proveido' => 'PROV-099-2026']);
        $carmen = $this->usuario('carmen@oti.local');

        $this->actingAs($carmen)->post("/derivaciones/{$derivacion->id}/cerrar", ['resolucion' => 'Listo'])->assertForbidden();
        $this->assertSame('abierta', $derivacion->fresh()->estado);
        $this->actingAs($carmen)->get('/derivaciones')->assertOk()->assertDontSee('PROV-099-2026');
        $this->actingAs($carmen)->post('/derivaciones', ['asignado_a' => $carmen->id, 'nro_proveido' => 'X', 'descripcion' => 'X'])->assertForbidden();
    }

    public function test_el_documento_escaneado_solo_lo_ven_mesa_jefe_y_el_asignado(): void
    {
        Storage::fake('local');
        Notification::fake();
        $luis = $this->usuario('luis@oti.local');

        $this->actingAs($this->usuario('mesa@oti.local'))->post('/derivaciones', [
            'asignado_a' => $luis->id, 'nro_proveido' => 'PROV-020-2026', 'descripcion' => 'Instalar proyector',
            'documento' => UploadedFile::fake()->create('proveido.pdf', 300, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $derivacion = Derivacion::firstOrFail();
        Storage::disk('local')->assertExists($derivacion->documento);

        $this->actingAs($luis)->get("/derivaciones/{$derivacion->id}/documento")->assertOk();
        $this->actingAs($this->usuario('jefe@oti.local'))->get("/derivaciones/{$derivacion->id}/documento")->assertOk();
        $this->actingAs($this->usuario('carmen@oti.local'))->get("/derivaciones/{$derivacion->id}/documento")->assertForbidden();

        $this->actingAs($this->usuario('mesa@oti.local'))->post('/derivaciones', [
            'asignado_a' => $luis->id, 'nro_proveido' => 'PROV-021-2026', 'descripcion' => 'X',
            'documento' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('documento');
    }

    public function test_solo_se_deriva_a_especialistas(): void
    {
        $this->actingAs($this->usuario('mesa@oti.local'))
            ->post('/derivaciones', ['asignado_a' => $this->usuario('jefe@oti.local')->id, 'nro_proveido' => 'PROV-1', 'descripcion' => 'X'])
            ->assertSessionHasErrors('asignado_a');
    }
}
