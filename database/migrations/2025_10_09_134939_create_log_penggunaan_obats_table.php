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
        Schema::create('log_penggunaan_obats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jadwal_obat_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('aktual_minum_obat');
            $table->dateTime('jadwal_minum_obat');
            $table->double('dosis_obat');
            $table->string('satuan');
            $table->text('catatan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_penggunaan_obats');
    }
};
