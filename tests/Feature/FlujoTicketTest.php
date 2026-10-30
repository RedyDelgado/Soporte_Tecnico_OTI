<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlujoTicketTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_flujo_completo_de_un_ticket(): void
    {
        $mesa = User::where('rol', 'mesa')->first();
        $luis = User::where('email', 'luis@oti.local')->first();
        $carmen = User::where('email', 'carmen@oti.local')->first();
        $cat = Categoria::where('nombre', 'Cuentas y contraseñas')->first();

        $caja = Area::create(['sede_id' => 1, 'nombre' => 'Caja']);
        $this->actingAs($mesa)->post('/tickets', [
            'sede_id' => 1, 'area_id' => $caja->id, 'nombre' => 'Eva', 'telefono' => '984000111',
            'canal' => 'whatsapp', 'categoria_id' => $cat->id, 'prioridad' => 'alta', 'descripcion' => 'No puede entrar al SIAF',
        ])->assertRedirect();

        $t = Ticket::latest('id')->first();
        $this->assertMatchesRegularExpression('/^OTI-\d{4}$/', $t->codigo);
        $this->assertSame('pendiente', $t->estado);
        $this->assertEqualsWithDelta(4 * 3600, $t->created_at->diffInSeconds($t->vence_at), 5);
        $this->assertSame('verde', $t->semaforo());

        // Un técnico no puede asignar; la mesa sí.
        $this->actingAs($luis)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $luis->id])->assertForbidden();
        $this->actingAs($mesa)->post("/tickets/{$t->id}/asignar", ['tecnico_id' => $luis->id])->assertRedirect();
        $this->assertSame('asignado', $t->fresh()->estado);

        // Solo el técnico asignado avanza y cierra.
        $this->actingAs($carmen)->post("/tickets/{$t->id}/estado", ['estado' => 'en_atencion'])->assertForbidden();
        $this->actingAs($luis)->post("/tickets/{$t->id}/estado", ['estado' => 'en_atencion'])->assertRedirect();

        // Cerrar exige diagnóstico y solución.
        $this->actingAs($luis)->post("/tickets/{$t->id}/resolver", ['categoria_id' => $cat->id, 'modalidad' => 'remota'])
            ->assertSessionHasErrors(['diagnostico', 'solucion']);
        $this->actingAs($luis)->post("/tickets/{$t->id}/resolver", [
            'categoria_id' => $cat->id, 'modalidad' => 'remota',
            'diagnostico' => 'Contraseña vencida en el SIAF', 'solucion' => 'Se restableció la clave y se verificó acceso',
        ])->assertRedirect('/tickets/mios');

        $t->refresh();
        $this->assertSame('resuelto', $t->estado);
        $this->assertSame('cumplido', $t->semaforo());
        $this->assertSame(['resuelto', 'en_atencion', 'asignado', 'pendiente'], $t->eventos->pluck('estado')->all());

        // La mesa puede reabrir.
        $this->actingAs($mesa)->post("/tickets/{$t->id}/reabrir", ['motivo' => 'Volvió a fallar'])->assertRedirect();
        $this->assertSame(1, $t->fresh()->reaperturas);

        // Seguimiento público sin login y páginas principales.
        auth()->logout();
        $this->get("/t/{$t->token}")->assertOk()->assertSee($t->codigo);
        $jefe = User::where('rol', 'jefe')->first();
        foreach (['/bandeja', '/tickets/nuevo', '/panel', '/soluciones?q=SIAF', '/categorias', '/usuarios', '/directorio', "/tickets/{$t->id}"] as $url) {
            $this->actingAs($jefe)->get($url)->assertOk();
        }
        $this->actingAs($luis)->get('/usuarios')->assertForbidden();
    }

    public function test_jefe_puede_impersonar_y_volver(): void
    {
        $jefe = User::where('rol', 'jefe')->first();
        $luis = User::where('email', 'luis@oti.local')->first();

        $this->actingAs($luis)->post("/usuarios/{$jefe->id}/impersonar")->assertForbidden();

        $this->actingAs($jefe)->post("/usuarios/{$luis->id}/impersonar")->assertRedirect('/tickets/mios');
        $this->assertAuthenticatedAs($luis);
        $this->get('/tickets/mios')->assertSee('Volver a mi cuenta');
        $this->post("/usuarios/{$jefe->id}/impersonar")->assertForbidden(); // sin impersonar en cadena

        $this->post('/impersonar/salir')->assertRedirect('/usuarios');
        $this->assertAuthenticatedAs($jefe);
        $this->post('/impersonar/salir')->assertForbidden();
    }

    public function test_persona_que_rota_no_se_duplica_y_conserva_su_historial(): void
    {
        $mesa = User::where('rol', 'mesa')->first();
        $tesoreria = Area::create(['sede_id' => 2, 'nombre' => 'Tesorería']);
        $rrhh = Area::create(['sede_id' => 2, 'nombre' => 'Recursos Humanos']);
        $pedido = fn ($extra) => $this->actingAs($mesa)->post('/tickets', $extra + [
            'sede_id' => 2, 'canal' => 'llamada', 'categoria_id' => 1, 'prioridad' => 'media', 'descripcion' => 'PC lenta',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $pedido(['area_id' => $tesoreria->id, 'nombre' => 'Ana Quispe', 'telefono' => '984111222']);
        $ana = Solicitante::where('telefono', '984111222')->first();

        // Mismo celular, sin elegirla del buscador: no se duplica y se mueve a RR. HH.
        $pedido(['area_id' => $rrhh->id, 'nombre' => 'Ana Quispe', 'telefono' => '984111222']);
        // Elegida del buscador.
        $pedido(['area_id' => $rrhh->id, 'nombre' => 'Ana Quispe', 'solicitante_id' => $ana->id]);

        $this->assertSame(1, Solicitante::count());
        $this->assertSame($rrhh->id, $ana->fresh()->area_id);
        $this->assertSame([$tesoreria->id, $rrhh->id, $rrhh->id], $ana->tickets()->orderBy('id')->pluck('area_id')->all());

        // Área de otra sede: rechazada.
        $this->actingAs($mesa)->post('/tickets', ['sede_id' => 1, 'area_id' => $rrhh->id, 'nombre' => 'X', 'canal' => 'llamada',
            'categoria_id' => 1, 'prioridad' => 'media', 'descripcion' => 'x'])->assertSessionHasErrors('area_id');

        // Área nueva duplicada en la misma sede: rechazada.
        $this->actingAs($mesa)->postJson('/areas', ['sede_id' => 2, 'nombre' => 'Tesorería'])->assertUnprocessable();
        $this->actingAs($mesa)->postJson('/areas', ['sede_id' => 1, 'nombre' => 'Tesorería'])->assertOk();

        // Buscador.
        $this->actingAs($mesa)->getJson('/personas/buscar?q=ana')->assertJsonPath('0.area', 'Recursos Humanos');
    }

    public function test_semaforo_pasa_a_rojo_al_vencer(): void
    {
        $area = Area::create(['sede_id' => 1, 'nombre' => 'Caja']);
        $eva = Solicitante::create(['nombre' => 'Eva', 'area_id' => $area->id]);
        $t = Ticket::create(['sede_id' => 1, 'area_id' => $area->id, 'solicitante_id' => $eva->id, 'canal' => 'llamada',
            'categoria_id' => 1, 'prioridad' => 'media', 'descripcion' => 'PC lenta', 'creado_por' => 1]);
        $this->travel(19)->hours();
        $this->assertSame('ambar', $t->semaforo());
        $this->travel(6)->hours();
        $this->assertSame('rojo', $t->semaforo());
    }
}
