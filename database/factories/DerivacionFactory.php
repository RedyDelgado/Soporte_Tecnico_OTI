<?php

namespace Database\Factories;

use App\Models\Derivacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Derivacion>
 */
class DerivacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asignado_a' => User::factory()->state(['rol' => 'tecnico', 'especialidad' => 'soporte']),
            'asignado_por' => User::factory()->state(['rol' => 'jefe']),
            'nro_proveido' => 'PROV-'.fake()->unique()->numerify('###').'-2026',
            'descripcion' => fake()->sentence(),
        ];
    }
}
