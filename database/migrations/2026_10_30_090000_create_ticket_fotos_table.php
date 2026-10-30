<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('etapa'); // pedido | cierre
            $table->string('ruta');
            $table->unsignedInteger('bytes');
            $table->timestamps();
        });

        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('evidencia'));
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->string('evidencia')->nullable());
        Schema::dropIfExists('ticket_fotos');
    }
};
