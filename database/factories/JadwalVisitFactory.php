<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JadwalVisit>
 */
class JadwalVisitFactory extends Factory
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
        $admin = User::whereHas('permissions', function ($q) {
            $q->where('name', 'Admin');
        })->inRandomOrder()->first();
        $keluhan = fake('id_ID')->sentence(2);
        $tgl_visit = fake()->dateTimeBetween($pasien->mulai_pengobatan, $pasien->selesai_pengobatan);
        return [
            'pasien_id' => $pasien->id,
            'admin_id' => $admin->id,
            'tgl_visit' => $tgl_visit,
            'keluhan' => $keluhan,
            'catatan' => fake('id_ID')->sentence(4),
            'active' => Carbon::createFromDate($tgl_visit)->isPast() ? false : true,
        ];
    }
}
