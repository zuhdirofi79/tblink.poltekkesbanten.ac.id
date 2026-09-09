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
        Schema::create('puskesmas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('nama_puskesmas', 200)->nullable();
            $table->string('kode_puskesmas', 100)->nullable();
            $table->string('kode_wilayah', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kotakab', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puskesmas');
    }
};
