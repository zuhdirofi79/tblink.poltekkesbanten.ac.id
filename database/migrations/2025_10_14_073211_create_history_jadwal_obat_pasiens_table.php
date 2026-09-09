<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('history_jadwal_obat_pasiens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pasien_id');
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('detail_jadwal_obat_id');
            $table->dateTime('waktu');
            $table->double('dosis', 15, 2);
            $table->string('satuan', 100)->nullable();
            $table->boolean('sudah_minum')->nullable();
            $table->dateTime('waktu_aktual_minum')->nullable();
            $table->json('notifikasi_dikirim')->nullable()->default('[]');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_jadwal_obat_pasiens');
    }
};
