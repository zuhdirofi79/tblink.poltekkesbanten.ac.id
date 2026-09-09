<?php

namespace Database\Factories;

use App\Models\Obat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ObatPasien>
 */
class ObatPasienFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pasien = User::whereHas('permissions', function ($q) {
            $q->where('name', 'Pasien');
        })->inRandomOrder()->first();
        $obat = Obat::inRandomOrder()->first();
        return [
            'pasien_id' => $pasien->id,
            'obat_id' => $obat->id,
        ];
    }
}
