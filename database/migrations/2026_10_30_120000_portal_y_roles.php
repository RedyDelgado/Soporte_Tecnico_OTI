<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Todo el que entra con su correo UNIQ es "usuario" (pide soporte) hasta que el administrador le dé otro rol.
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('usuario')->change();
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->timestamp('ultimo_ingreso')->nullable();
        });

        // Vincula a la persona del directorio con su cuenta (mismo correo).
        Schema::table('solicitantes', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('telefono');
        });

        // Para pedidos del portal en los que el usuario no sabe qué tipo de problema es; la mesa lo reclasifica.
        if (! DB::table('categorias')->where('nombre', 'Por clasificar')->exists()) {
            DB::table('categorias')->insert(['nombre' => 'Por clasificar', 'especialidad' => 'soporte', 'prioridad' => 'media', 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('solicitantes', fn (Blueprint $table) => $table->dropColumn('email'));
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('tecnico')->change();
            $table->dropColumn(['google_id', 'ultimo_ingreso']);
        });
    }
};
