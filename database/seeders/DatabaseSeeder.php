<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Sede;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Jr. Kiteni', 'Quillabamba', 'Potrero', 'El Arenal'] as $s) {
            Sede::create(['nombre' => $s]);
        }

        $categorias = [
            'soporte' => ['PC no enciende o lenta' => 'media', 'Impresora o escáner' => 'media', 'Instalación de software' => 'baja',
                'Correo institucional' => 'media', 'Ofimática y antivirus' => 'baja', 'Cuentas y contraseñas' => 'alta', 'Otros soporte' => 'baja'],
            'redes' => ['Sin internet' => 'alta', 'Wi-Fi' => 'media', 'Cableado o punto de red' => 'media', 'Cámaras CCTV' => 'media',
                'Telefonía IP' => 'media', 'Servidor o sistema caído' => 'alta', 'Otros redes' => 'baja'],
            'web' => ['Publicación en gob.pe' => 'media', 'Portal de Transparencia (PTE)' => 'media', 'Página web institucional' => 'baja'],
        ];
        foreach ($categorias as $esp => $items) {
            foreach ($items as $nombre => $prioridad) {
                Categoria::create(['nombre' => $nombre, 'especialidad' => $esp, 'prioridad' => $prioridad]);
            }
        }

        // Cambiar las contraseñas al pasar a producción.
        $password = 'oti12345';
        $jefe = User::create(['name' => 'Jefe OTI', 'email' => 'jefe@oti.local', 'password' => $password, 'rol' => 'jefe']);
        $mesa = User::create(['name' => 'Mesa de ayuda', 'email' => 'mesa@oti.local', 'password' => $password, 'rol' => 'mesa']);
        $luis = User::create(['name' => 'Luis', 'email' => 'luis@oti.local', 'password' => $password, 'rol' => 'tecnico', 'especialidad' => 'soporte']);
        User::create(['name' => 'Carmen', 'email' => 'carmen@oti.local', 'password' => $password, 'rol' => 'tecnico', 'especialidad' => 'soporte']);
        $jorge = User::create(['name' => 'Jorge', 'email' => 'jorge@oti.local', 'password' => $password, 'rol' => 'tecnico', 'especialidad' => 'redes']);

        if (! app()->isLocal()) {
            return;
        }

        $demo = [
            [2, 'Tesorería', 'Ana Quispe', '984111222', 'whatsapp', 'Impresora o escáner', 'La impresora no imprime, sale hoja en blanco'],
            [1, 'Mesa de partes', 'Pedro Huamán', '984333444', 'llamada', 'Sin internet', 'No hay internet en toda la oficina'],
            [4, 'Recursos Humanos', 'Rosa Mamani', null, 'correo', 'Portal de Transparencia (PTE)', 'Publicar convocatoria CAS 012-2026 en el PTE'],
            [3, 'Contabilidad', 'Juan Ccori', '984555666', 'whatsapp', 'PC no enciende o lenta', 'La PC demora 10 minutos en encender'],
        ];
        foreach ($demo as [$sede, $area, $nombre, $tel, $canal, $cat, $desc]) {
            $c = Categoria::where('nombre', $cat)->first();
            $a = Area::firstOrCreate(['sede_id' => $sede, 'nombre' => $area]);
            $p = Solicitante::registrar(null, $nombre, $tel, $a->id);
            Ticket::create(['sede_id' => $sede, 'area_id' => $a->id, 'solicitante_id' => $p->id, 'canal' => $canal,
                'categoria_id' => $c->id, 'prioridad' => $c->prioridad, 'descripcion' => $desc, 'creado_por' => $mesa->id]);
        }

        $t = Ticket::find(4);
        $t->tecnico_id = $luis->id;
        $t->cambiarEstado('asignado', $mesa);
        $t->cambiarEstado('en_atencion', $luis);
    }
}
