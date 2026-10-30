<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los pedidos del formulario público (personas sin correo UNIQ) no los registra ningún usuario del sistema:
     * llegan en estado "por_validar" hasta que la mesa de ayuda los aprueba o los rechaza.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('creado_por')->nullable()->change();
            $table->string('motivo_rechazo')->nullable()->after('solucion');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('motivo_rechazo');
        });
    }
};
