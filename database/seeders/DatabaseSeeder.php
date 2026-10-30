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
    /**
     * Datos base (sedes y categorías): siempre, y se puede correr varias veces sin duplicar.
     * Cuentas y tickets de prueba: solo en desarrollo y en las pruebas, nunca en producción.
     */
    public function run(): void
    {
        foreach (['Jr. Kiteni', 'Quillabamba', 'Potrero', 'El Arenal'] as $s) {
            Sede::firstOrCreate(['nombre' => $s]);
        }

        $categorias = [
            'soporte' => ['PC no enciende o lenta' => 'media', 'Impresora o escáner' => 'media', 'Instalación de software' => 'baja',
                'Correo institucional' => 'media', 'Ofimática y antivirus' => 'baja', 'Cuentas y contraseñas' => 'alta', 'Otros soporte' => 'baja',
                'Por clasificar' => 'media'],
            'redes' => ['Sin internet' => 'alta', 'Wi-Fi' => 'media', 'Cableado o punto de red' => 'media', 'Cámaras CCTV' => 'media',
                'Telefonía IP' => 'media', 'Otros redes' => 'baja'],
            'servidores' => ['Servidor o sistema caído' => 'alta', 'Copias de respaldo' => 'media', 'Base de datos o sistema institucional' => 'alta'],
            'web' => ['Publicación en gob.pe' => 'media', 'Portal de Transparencia (PTE)' => 'media', 'Página web institucional' => 'baja'],
        ];
        foreach ($categorias as $esp => $items) {
            foreach ($items as $nombre => $prioridad) {
                Categoria::firstOrCreate(['nombre' => $nombre], ['especialidad' => $esp, 'prioridad' => $prioridad]);
            }
        }

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $password = 'oti12345';
        $cuentas = [
            ['Jefe OTI', 'jefe@oti.local', 'jefe', null], ['Mesa de ayuda', 'mesa@oti.local', 'mesa', null],
            ['Luis', 'luis@oti.local', 'tecnico', 'soporte'], ['Carmen', 'carmen@oti.local', 'tecnico', 'soporte'],
            ['Jorge', 'jorge@oti.local', 'tecnico', 'redes'],
        ];
        foreach ($cuentas as [$nombre, $email, $rol, $especialidad]) {
            User::firstOrCreate(['email' => $email], ['name' => $nombre, 'password' => $password, 'rol' => $rol, 'especialidad' => $especialidad]);
        }

        if (! app()->isLocal() || Ticket::exists()) {
            return;
        }

        $mesa = User::where('email', 'mesa@oti.local')->first();
        $luis = User::where('email', 'luis@oti.local')->first();
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

        $t = Ticket::latest('id')->first();
        $t->tecnico_id = $luis->id;
        $t->cambiarEstado('asignado', $mesa);
        $t->cambiarEstado('en_atencion', $luis);
    }
}
