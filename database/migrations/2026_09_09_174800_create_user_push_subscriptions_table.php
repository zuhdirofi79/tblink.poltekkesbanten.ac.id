<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-device FCM registrations.
 *
 * DO NOT run against production until explicitly approved.
 * php artisan migrate:fresh is forbidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('device_label')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('platform', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_subscriptions');
    }
};
