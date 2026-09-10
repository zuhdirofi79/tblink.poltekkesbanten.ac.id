<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_jadwal_obat_pasiens', function (Blueprint $table) {
            $table->string('source', 32)->default('schedule');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('history_jadwal_obat_pasiens', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
