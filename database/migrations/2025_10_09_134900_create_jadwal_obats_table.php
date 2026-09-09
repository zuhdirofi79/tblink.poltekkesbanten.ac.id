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
        Schema::create('jadwal_obats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('pasien_id');
            $table->unsignedBigInteger('obat_pasien_id');
            $table->double('qty_diberikan', 15, 2)->nullable();
            $table->string('satuan', 100)->nullable();
            $table->date('mulai_tanggal');
            $table->date('selesai_tanggal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_obats');
    }
};
