<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

/** Portal público, ingreso con Google UNIQ, roles, perfiles y reportes. */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Simula las respuestas de Google, en orden, para cada ingreso del test. */
    private function google(array $cuentas): void
    {
        config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);
        $usuarios = array_map(fn ($c) => (new GoogleUser)->setRaw(['hd' => $c[1]])->map(['id' => 'g-'.$c[0], 'name' => 'Rosa Mamani', 'email' => $c[0]]), $cuentas);
        Socialite::shouldReceive('driver->user')->andReturn(...$usuarios);
    }

    public function test_pagina_publica_muestra_qr_e_ingreso(): void
    {
        $this->get('/login')->assertOk()->assertSee('Con tu correo UNIQ, desde el celular')->assertSee('<svg', false);
    }

    public function test_google_solo_acepta_correo_uniq_y_crea_usuario(): void
    {
        $this->google([['alguien@gmail.com', null], ['falso@uniq.edu.pe', null], ['rosa.mamani@uniq.edu.pe', 'uniq.edu.pe']]);
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->get('/auth/google/callback')->assertRedirect('/login'); // correo UNIQ pero no de su Google Workspace
        $this->assertGuest();

        $this->get('/auth/google/callback')->assertRedirect('/mis-pedidos');
        $u = User::where('email', 'rosa.mamani@uniq.edu.pe')->first();
        $this->assertSame('usuario', $u->rol);
        $this->assertNotNull($u->ultimo_ingreso);
        $this->assertAuthenticatedAs($u);
    }

    public function test_usuario_registra_pedido_desde_el_portal_y_no_ve_lo_interno(): void
    {
        $u = User::create(['name' => 'Rosa Mamani', 'email' => 'rosa@uniq.edu.pe', 'password' => 'x', 'rol' => 'usuario']);
        $area = Area::create(['sede_id' => 2, 'nombre' => 'Biblioteca']);
        // La mesa ya la tenía en el directorio por su celular: se vincula, no se duplica.
        Solicitante::create(['nombre' => 'Rosa M.', 'telefono' => '984000999', 'area_id' => $area->id]);

        $this->actingAs($u)->post('/mis-pedidos', ['sede_id' => 2, 'area_id' => $area->id, 'telefono' => '984000999', 'descripcion' => 'No enciende el proyector'])
            ->assertRedirect('/mis-pedidos');

        $t = Ticket::latest('id')->first();
        $this->assertSame('portal', $t->canal);
        $this->assertSame('Por clasificar', $t->categoria->nombre);
        $this->assertSame(1, Solicitante::count());
        $this->assertSame('rosa@uniq.edu.pe', $t->solicitante->email);
        $this->actingAs($u)->get('/mis-pedidos')->assertSee($t->codigo);

        foreach (['/bandeja', '/panel', '/tickets/'.$t->id, '/usuarios', '/reportes', '/directorio'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }

        // La mesa lo clasifica y el plazo se recalcula.
        $mesa = User::where('rol', 'mesa')->first();
        $cat = Categoria::where('nombre', 'Sin internet')->first();
        $this->actingAs($mesa)->put("/tickets/{$t->id}", ['categoria_id' => $cat->id, 'prioridad' => 'alta']
            + $t->only('sede_id', 'area_id', 'canal', 'descripcion'))->assertRedirect();
        $this->assertEqualsWithDelta(4 * 3600, $t->fresh()->created_at->diffInSeconds($t->fresh()->vence_at), 5);
    }

    public function test_solo_el_administrador_cambia_roles_y_ve_perfiles(): void
    {
        $jefe = User::where('rol', 'jefe')->first();
        $mesa = User::where('rol', 'mesa')->first();
        $u = User::create(['name' => 'Pedro', 'email' => 'pedro@uniq.edu.pe', 'password' => 'x', 'rol' => 'usuario']);

        $this->actingAs($mesa)->put("/usuarios/{$u->id}", ['name' => 'Pedro', 'rol' => 'tecnico'])->assertForbidden();
        $this->actingAs($jefe)->put("/usuarios/{$u->id}", ['name' => 'Pedro', 'rol' => 'tecnico', 'especialidad' => 'servidores', 'activo' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['tecnico', 'servidores'], [$u->fresh()->rol, $u->fresh()->especialidad]);

        $this->actingAs($jefe)->get("/usuarios/{$u->id}")->assertOk()->assertSee('Rol y acceso');
        $this->actingAs($jefe)->get('/usuarios?rol=tecnico')->assertSee('Pedro');

        // Registrar de antemano exige correo UNIQ.
        $this->actingAs($jefe)->post('/usuarios', ['name' => 'X', 'email' => 'x@gmail.com', 'rol' => 'mesa'])->assertSessionHasErrors('email');
        $this->actingAs($jefe)->post('/usuarios', ['name' => 'Ana', 'email' => 'ana@uniq.edu.pe', 'rol' => 'mesa'])->assertSessionHasNoErrors();
    }

    public function test_reportes_por_area_persona_y_problema(): void
    {
        $jefe = User::where('rol', 'jefe')->first();
        $area = Area::create(['sede_id' => 1, 'nombre' => 'Caja']);
        $p = Solicitante::create(['nombre' => 'Eva Ccori', 'area_id' => $area->id]);
        $cat = Categoria::where('nombre', 'Impresora o escáner')->first();
        foreach (range(1, 3) as $_) {
            Ticket::create(['sede_id' => 1, 'area_id' => $area->id, 'solicitante_id' => $p->id, 'canal' => 'llamada',
                'categoria_id' => $cat->id, 'prioridad' => 'media', 'descripcion' => 'Atasco de papel', 'creado_por' => $jefe->id]);
        }

        $this->actingAs($jefe)->get('/reportes')->assertOk()
            ->assertSee('Caja')->assertSee('Eva Ccori')->assertSee('Impresora o escáner')
            ->assertSee('Problemas que se repiten');
        $this->actingAs($jefe)->get('/reportes?csv=areas')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($jefe)->get("/reportes/detalle?area={$area->id}")->assertOk()->assertSee('Atasco de papel');
        $this->actingAs($jefe)->get("/reportes/detalle?solicitante={$p->id}&csv=1")->assertOk();
    }

    public function test_tecnico_ve_su_avance_y_el_de_sus_companeros(): void
    {
        $jefe = User::where('rol', 'jefe')->first();
        [$yo, $otro] = User::where('rol', 'tecnico')->take(2)->get();
        $area = Area::create(['sede_id' => 1, 'nombre' => 'Caja']);
        $p = Solicitante::create(['nombre' => 'Eva Ccori', 'area_id' => $area->id]);
        $cat = Categoria::first();
        foreach ([$yo, $otro, $otro] as $tecnico) {
            $t = Ticket::create(['sede_id' => 1, 'area_id' => $area->id, 'solicitante_id' => $p->id, 'canal' => 'llamada',
                'categoria_id' => $cat->id, 'prioridad' => 'media', 'descripcion' => 'Sin red', 'creado_por' => $jefe->id]);
            $t->tecnico_id = $tecnico->id;
            $t->save();
        }

        $this->actingAs($yo)->get('/reportes')->assertOk()
            ->assertSee('Avance del equipo técnico')->assertSeeInOrder([$otro->name, $yo->name, '(tú)']);
        $this->actingAs($yo)->get('/reportes?csv=tecnicos')->assertOk();
        $this->actingAs($yo)->get("/reportes/detalle?tecnico={$otro->id}")->assertOk()->assertSee('Pedidos atendidos por '.$otro->name);
    }
}
