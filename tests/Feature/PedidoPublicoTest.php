<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pedidos de personas sin correo UNIQ: formulario público, validación de la mesa y seguimiento. */
class PedidoPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function datos(array $extra = []): array
    {
        return $extra + [
            'nombre' => 'Juana Ccahua', 'telefono' => '984000777', 'email' => 'juana@gmail.com',
            'sede_id' => 2, 'area_nueva' => 'Biblioteca central', 'descripcion' => 'No funciona la impresora',
        ];
    }

    public function test_la_pagina_de_ingreso_ya_no_muestra_contrasena_y_tiene_el_segundo_qr(): void
    {
        $this->get('/login')->assertOk()
            ->assertDontSee('Ingreso con contraseña')
            ->assertDontSee('name="password"', false)
            ->assertSee('¿No tienes correo institucional?')
            ->assertSee(route('pedido.publico'));

        config(['oti.login_password' => false]);
        $this->get('/login/clave')->assertNotFound();
        config(['oti.login_password' => true]);
        $this->get('/login/clave')->assertOk()->assertSee('name="password"', false);
    }

    public function test_cualquiera_registra_un_pedido_que_queda_por_validar(): void
    {
        $this->get('/pedido')->assertOk()->assertSee('Pedir soporte a la OTI');

        $r = $this->post('/pedido', $this->datos());
        $t = Ticket::latest('id')->first();
        $r->assertRedirect(route('seguimiento', $t->token));

        $this->assertSame(['por_validar', 'formulario', 'Por clasificar', null], [$t->estado, $t->canal, $t->categoria->nombre, $t->creado_por]);
        // Escribió un área que ya está en la lista: se reutiliza, no se duplica.
        $this->assertSame('Biblioteca Central', $t->area->nombre);
        $this->assertSame(1, Area::where('sede_id', $t->sede_id)->where('nombre', 'Biblioteca Central')->count());
        $this->assertSame('juana@gmail.com', $t->solicitante->email);
        $this->get("/t/{$t->token}")->assertSee('La mesa de ayuda lo está validando');

        // No entra a la cola ni a los indicadores hasta que se valide.
        $this->assertSame(0, Ticket::abiertos()->count() - Ticket::abiertos()->where('id', '!=', $t->id)->count());
        $this->assertFalse(Ticket::validos()->whereKey($t->id)->exists());
    }

    public function test_no_sobrescribe_a_una_persona_que_ya_esta_en_el_directorio(): void
    {
        $area = Area::create(['sede_id' => 1, 'nombre' => 'Tesorería']);
        $p = Solicitante::create(['nombre' => 'Juana Ccahua Quispe', 'telefono' => '984000777', 'area_id' => $area->id]);

        $this->post('/pedido', $this->datos(['nombre' => 'Otro nombre']));

        $this->assertSame(1, Solicitante::where('telefono', '984000777')->count());
        $this->assertSame('Juana Ccahua Quispe', $p->fresh()->nombre);
        $this->assertSame($p->id, Ticket::latest('id')->first()->solicitante_id);
    }

    public function test_campo_trampa_y_validaciones(): void
    {
        $this->post('/pedido', $this->datos(['sitio_web' => 'http://spam']))->assertRedirect(route('pedido.publico'));
        $this->assertSame(0, Ticket::count());

        $this->post('/pedido', $this->datos(['telefono' => 'llámame']))->assertSessionHasErrors('telefono');
        $this->post('/pedido', $this->datos(['area_nueva' => null]))->assertSessionHasErrors('area_id');
    }

    public function test_la_mesa_valida_o_rechaza(): void
    {
        $mesa = User::where('rol', 'mesa')->first();
        $luis = User::where('email', 'luis@oti.local')->first();
        $this->post('/pedido', $this->datos());
        $this->post('/pedido', $this->datos(['telefono' => '984000888', 'email' => null]));
        [$a, $b] = Ticket::orderBy('id')->get()->all();

        $this->actingAs($mesa)->get('/bandeja?f=val')->assertSee($a->codigo)->assertSee('Validar');

        // Sin validar no se puede asignar.
        $this->actingAs($mesa)->post("/tickets/{$a->id}/asignar", ['tecnico_id' => $luis->id])->assertForbidden();

        $this->actingAs($mesa)->post("/tickets/{$a->id}/validar")->assertRedirect();
        $a->refresh();
        $this->assertSame('pendiente', $a->estado);
        $this->assertEqualsWithDelta(24 * 3600, now()->diffInSeconds($a->vence_at), 10);
        $this->actingAs($mesa)->post("/tickets/{$a->id}/asignar", ['tecnico_id' => $luis->id])->assertRedirect();

        $this->actingAs($mesa)->post("/tickets/{$b->id}/rechazar", ['motivo' => 'No pertenece a la UNIQ'])->assertRedirect();
        $this->assertSame('rechazado', $b->fresh()->estado);
        $this->get("/t/{$b->token}")->assertSee('No pertenece a la UNIQ');
        $this->actingAs($mesa)->post("/tickets/{$b->id}/validar")->assertForbidden();

        // Un técnico no valida.
        $this->post('/pedido', $this->datos(['telefono' => '984000999']));
        $c = Ticket::latest('id')->first();
        $this->actingAs($luis)->post("/tickets/{$c->id}/validar")->assertForbidden();
    }
}
