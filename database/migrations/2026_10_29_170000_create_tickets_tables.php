<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained();
            $table->string('nombre');
            $table->timestamps();
            $table->unique(['sede_id', 'nombre']);
        });

        // Persona que pide atención. area_id es su área ACTUAL; cada ticket guarda la suya del momento.
        Schema::create('solicitantes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->index();
            $table->string('telefono', 20)->nullable()->unique();
            $table->foreignId('area_id')->constrained();
            $table->timestamps();
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('especialidad'); // soporte | redes | web
            $table->string('prioridad')->default('media'); // alta | media | baja
            $table->boolean('activa')->default(true);
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->string('token', 32)->unique();
            $table->foreignId('sede_id')->constrained();
            $table->foreignId('area_id')->constrained();
            $table->foreignId('solicitante_id')->constrained();
            $table->string('canal'); // whatsapp | correo | llamada | presencial
            $table->foreignId('categoria_id')->constrained();
            $table->string('prioridad');
            $table->text('descripcion');
            $table->string('estado')->default('pendiente')->index();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por')->constrained('users');
            $table->text('diagnostico')->nullable();
            $table->text('solucion')->nullable();
            $table->string('modalidad')->nullable(); // remota | presencial
            $table->string('evidencia')->nullable();
            $table->unsignedTinyInteger('reaperturas')->default(0);
            $table->timestamp('vence_at');
            $table->timestamp('asignado_at')->nullable();
            $table->timestamp('atencion_at')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('estado');
            $table->string('nota')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_eventos');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('solicitantes');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('sedes');
    }
};
