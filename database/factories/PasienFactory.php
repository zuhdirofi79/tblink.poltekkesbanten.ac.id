<?php

namespace Database\Factories;

use App\Models\Puskesmas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pasien>
 */
class PasienFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $puskesmas = Puskesmas::inRandomOrder()->first();
        $diagnosa = Carbon::createFromDate(fake()->dateTimeBetween('-4 months', 'now'));
        $jenjang = [3, 6, 12][rand(0, 2)];
        $user = User::factory()->create();
        $user->givePermissionTo('Pasien');
        $pendamping = User::factory()->create();
        $pendamping->givePermissionTo('Pendamping');
        $name = fake('id_ID')->name();
        $user->name = $name;
        $user->save();
        return [
            'nama' => $name,
            'puskesmas_id' => $puskesmas->id,
            'nik' => fake('id_ID')->numberBetween(30000000, 99999999),
            'tgl_lahir' => fake()->date('Y-m-d', now()->subYear(30)),
            'jenis_kelamin' => ['Laki-laki', 'Perempuan'][rand(0, 1)],
            'alamat' => fake('id_ID')->address(),
            'tipe_tb' => ['TB Paru', 'TB Kelenjar', 'TB Selaput Otak', 'TB Usus', 'TB Ginjal'][rand(0, 4)],
            'tgl_diagnosa' => $diagnosa,
            'mulai_pengobatan' => $diagnosa,
            'selesai_pengobatan' => $diagnosa->clone()->addDays($jenjang * 28),
            'pendamping_id' => $pendamping->id,
            'jenjang_terapi' => $jenjang,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ];
    }
}
