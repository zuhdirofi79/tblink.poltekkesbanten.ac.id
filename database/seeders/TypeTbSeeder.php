<?php

namespace Database\Seeders;

use App\Models\TypeTb;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeTbSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TypeTb::firstOrCreate(['tipe_tb' => 'TB Paru']);
        TypeTb::firstOrCreate(['tipe_tb' => 'TB Kelenjar']);
        TypeTb::firstOrCreate(['tipe_tb' => 'TB Selaput Otak']);
        TypeTb::firstOrCreate(['tipe_tb' => 'TB Usus']);
        TypeTb::firstOrCreate(['tipe_tb' => 'TB Ginjal']);
    }
}
