<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional production column for JadwalVisit reminder idempotency.
 *
 * DO NOT run automatically against production.
 * Until this is applied, VisitReminderTracker uses a private JSON file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_visits', function (Blueprint $table) {
            $table->timestamp('notification_sent_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_visits', function (Blueprint $table) {
            $table->dropColumn('notification_sent_at');
        });
    }
};
