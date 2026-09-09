<?php

namespace Database\Seeders;

use App\Models\LiveChat;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LiveChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $period = CarbonPeriod::create(now()->subHour(), now(), '30 seconds');
        $user = User::whereHas('permissions', function ($p) {
            $p->where('name', 'Admin');
        })->inRandomOrder()->first();
        $pasien = User::whereHas('permissions', function ($p) {
            $p->where('name', 'Pasien');
        })->inRandomOrder()->first();
        foreach ($period as $key =>  $per) {
            if ($key % 2 == 0) {
                $sender = $user;
                $receiver = $pasien;
            } else {
                $sender = $pasien;
                $receiver = $user;
            }
            LiveChat::insert([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'message' => fake()->text(),
                'read_at' => $per->clone()->addSeconds(rand(0, 30)),
                'created_at' => $per,
                'updated_at' => $per,
            ]);
        }
    }
}
