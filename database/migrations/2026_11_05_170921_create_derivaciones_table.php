<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Trabajo que el jefe o la mesa deriva a un especialista con su número de proveído. No depende de un ticket.
    public function up(): void
    {
        Schema::create('derivaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignado_a')->constrained('users');
            $table->foreignId('asignado_por')->constrained('users');
            $table->string('nro_proveido', 50);
            $table->text('descripcion');
            $table->string('estado')->default('abierta'); // abierta | cerrada
            $table->text('resolucion')->nullable();
            $table->foreignId('cerrada_por')->nullable()->constrained('users');
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
            $table->index(['asignado_a', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derivaciones');
    }
};
