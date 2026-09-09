<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JadwalObat>
 */
class JadwalObatFactory extends Factory
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
        $mulai = fake()->dateTimeBetween($pasien->mulai_pengobatan, $pasien->selesai_pengobatan);
        return [
            'obat_id' => $obat->id,
            'pasien_id' => $pasien->id,
            'mulai_tanggal' => $mulai,
            'selesai_tanggal' => fake()->dateTimeBetween($mulai, $pasien->selesai_pengobatan),
        ];
    }
}
