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
        Schema::create('pasiens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nik');
            $table->string('nama');
            $table->date('tgl_lahir');
            $table->string('jenis_kelamin');
            $table->string('alamat')->nullable();
            $table->string('tipe_tb')->nullable();
            $table->integer('jenjang_terapi')->default(3);
            $table->date('tgl_diagnosa');
            $table->date('mulai_pengobatan');
            $table->date('selesai_pengobatan');
            $table->unsignedBigInteger('pendamping_id')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pasiens');
    }
};
