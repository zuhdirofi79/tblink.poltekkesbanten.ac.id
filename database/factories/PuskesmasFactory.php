<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Puskesmas>
 */
class PuskesmasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $u = User::create([
            'username' => 'puskesmas_' . strtolower(fake()->userName()),
            'name' => 'Puskesmas ' . fake()->name(),
            'password' => bcrypt('password'),
        ]);
        $u->givePermissionTo('Admin');
        return [
            'user_id' => $u->id,
            'nama_puskesmas' => $u->name,
            'alamat' => fake('id_ID')->address(),
            'kode_puskesmas' => fake()->postcode(),
            'kecamatan' => fake('id_ID')->word(),
            'kotakab' => fake('id_ID')->city(),
            'provinsi' => fake('id_ID')->state(),
        ];
    }
}
