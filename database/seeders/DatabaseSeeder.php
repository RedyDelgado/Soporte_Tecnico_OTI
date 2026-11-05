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

        $areas = ['Rectorado (Presidencia de la Comisión Organizadora)', 'Vicerrectorado Académico', 'Vicerrectorado de Investigación',
            'Órgano de Control Institucional', 'Oficina de Asesoría Jurídica', 'Oficina de Planeamiento y Presupuesto',
            'Unidad de Planeamiento, Estadística y Modernización (UPEM)', 'Unidad de Presupuesto', 'Unidad Formuladora',
            'Oficina de Cooperación y Relaciones Internacionales', 'Oficina de Gestión de la Calidad', 'Oficina de Comunicación e Imagen Institucional',
            'Oficina de Tecnologías de la Información', 'Dirección General de Administración', 'Unidad de Recursos Humanos',
            'Unidad de Abastecimiento', 'Unidad de Contabilidad', 'Unidad de Tesorería', 'Unidad de Servicios Generales',
            'Unidad Ejecutora de Inversiones', 'Secretaría General', 'Unidad de Trámite Documentario y Archivo', 'Unidad de Grados y Títulos',
            'Dirección de Servicios Académicos', 'Dirección de Admisión', 'Biblioteca Central', 'Dirección de Bienestar Universitario',
            'Dirección de Responsabilidad Social', 'Dirección de Interculturalidad', 'Unidad de Servicios Educativos de Extensión',
            'Dirección de Institutos de Investigación', 'Facultad de Gestión y Ciencias Sociales', 'Facultad de Ingenierías',
            'Departamento Académico de Ciencias Básicas', 'Departamento Académico de Ciencias Sociales', 'Departamento Académico de Ecoturismo',
            'Departamento Académico de Ingeniería Agronómica Tropical', 'Departamento Académico de Ingeniería Civil',
            'Departamento Académico de Ingeniería de Alimentos', 'Escuela Profesional de Ingeniería Civil',
            'Escuela Profesional de Ingeniería Agronómica Tropical', 'Escuela Profesional de Ingeniería de Alimentos',
            'Escuela Profesional de Ecoturismo', 'Escuela Profesional de Economía', 'Escuela Profesional de Contabilidad',
            'Unidades de Investigación', 'Unidades de Posgrado', 'Escuela de Posgrado'];
        foreach (Sede::all() as $sede) {
            foreach ($areas as $nombre) {
                Area::firstOrCreate(['sede_id' => $sede->id, 'nombre' => $nombre]);
            }
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
