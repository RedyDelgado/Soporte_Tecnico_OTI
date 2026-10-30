<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cola por prioridad, varios técnicos en un ticket y aviso al usuario cuando el equipo está ocupado. */
class ColaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function ticket(string $prioridad, ?Solicitante $p = null): Ticket
    {
        $area = Area::firstOrCreate(['sede_id' => 1, 'nombre' => 'Caja']);
        $p ??= Solicitante::create(['nombre' => 'Eva '.uniqid(), 'area_id' => $area->id]);

        return Ticket::create(['sede_id' => 1, 'area_id' => $area->id, 'solicitante_id' => $p->id, 'canal' => 'llamada',
            'categoria_id' => 1, 'prioridad' => $prioridad, 'descripcion' => "Pedido $prioridad", 'creado_por' => 1]);
    }

    private function u(string $email): User
    {
        return User::where('email', $email)->first();
    }

    public function test_cola_del_tecnico_va_por_prioridad(): void
    {
        $mesa = $this->u('mesa@oti.local');
        $luis = $this->u('luis@oti.local');
        $baja = $this->ticket('baja');
        $alta = $this->ticket('alta');
        $media = $this->ticket('media');
        foreach ([$baja, $alta, $media] as $t) {
            $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $luis->id]);
        }

        $this->actingAs($luis)->get('/tickets/mios')->assertOk()
            ->assertSeeInOrder(['Atender ahora', $alta->codigo, $media->codigo, $baja->codigo]);
    }

    public function test_dos_o_mas_tecnicos_en_un_mismo_ticket(): void
    {
        $mesa = $this->u('mesa@oti.local');
        [$luis, $jorge, $carmen] = [$this->u('luis@oti.local'), $this->u('jorge@oti.local'), $this->u('carmen@oti.local')];
        $t = $this->ticket('alta');
        $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $luis->id]);

        // Apoyo: se ignora al responsable si viene en la lista.
        $this->actingAs($mesa)->post("/tickets/{$t->id}/apoyos", ['apoyos' => [$jorge->id, $carmen->id, $luis->id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$jorge->id, $carmen->id], $t->apoyos()->pluck('users.id')->all());
        $this->assertStringContainsString('Apoyo:', $t->eventos()->first()->nota);

        // El apoyo lo ve en su cola y puede avanzarlo y cerrarlo.
        $this->actingAs($jorge)->get('/tickets/mios')->assertSee($t->codigo)->assertSee('Vas de apoyo de Luis');
        $this->actingAs($jorge)->post("/tickets/{$t->id}/estado", ['estado' => 'en_camino'])->assertSessionHasNoErrors();
        $this->assertSame('en_camino', $t->fresh()->estado);

        // Un técnico no puede armar el equipo; la mesa sí puede quitar el apoyo.
        $this->actingAs($jorge)->post("/tickets/{$t->id}/apoyos", ['apoyos' => []])->assertForbidden();
        $this->actingAs($mesa)->post("/tickets/{$t->id}/apoyos", ['apoyos' => [$jorge->id]]);
        $this->assertSame([$jorge->id], $t->apoyos()->pluck('users.id')->all());

        // Si un apoyo pasa a ser responsable, sale de la lista de apoyo.
        $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $jorge->id]);
        $this->assertSame(0, $t->apoyos()->count());
    }

    public function test_el_usuario_ve_que_el_equipo_esta_ocupado(): void
    {
        $mesa = $this->u('mesa@oti.local');
        $tecnicos = User::where('rol', 'tecnico')->get();
        // Todos los técnicos atendiendo algo.
        foreach ($tecnicos as $tec) {
            $t = $this->ticket('media');
            $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $tec->id]);
            $this->actingAs($tec)->post("/tickets/{$t->id}/estado", ['estado' => 'en_atencion']);
        }
        $this->assertTrue(Ticket::disponibilidad()['todos_ocupados']);

        $u = User::create(['name' => 'Rosa', 'email' => 'rosa@uniq.edu.pe', 'password' => 'x']);
        $this->actingAs($u)->get('/mis-pedidos/nuevo')->assertSee('En este momento nuestros técnicos están ocupados');

        $area = Area::firstOrCreate(['sede_id' => 1, 'nombre' => 'Caja']);
        $this->actingAs($u)->post('/mis-pedidos', ['sede_id' => 1, 'area_id' => $area->id, 'descripcion' => 'Sin red'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'apenas se libere uno'));
        $mio = Ticket::latest('id')->first();
        $this->assertSame('ocupado', $mio->mensajeParaSolicitante()[0]);

        // Asignado a alguien que está atendiendo otro: "irá apenas termine".
        $luis = $this->u('luis@oti.local');
        $this->actingAs($mesa)->post("/tickets/{$mio->id}/asignar", ['tecnico_id' => $luis->id]);
        $this->get("/t/{$mio->token}")->assertSee('Luis está atendiendo otro pedido');

        // Cuando todos se liberan, ya no se muestra el aviso.
        Ticket::where('estado', 'en_atencion')->update(['estado' => 'resuelto', 'resuelto_at' => now()]);
        $this->assertFalse(Ticket::disponibilidad()['todos_ocupados']);
        $this->assertSame('espera', $mio->fresh()->mensajeParaSolicitante()[0]);
    }
}
