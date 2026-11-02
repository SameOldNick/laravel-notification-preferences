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
        Schema::create('notifiable_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notification_type');
            $table->string('channel');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(
                ['notifiable_type', 'notifiable_id'],
                'notifiable_pref_notifiable_idx'
            );
            $table->unique(
                ['notifiable_type', 'notifiable_id', 'notification_type', 'channel'],
                'notifiable_notification_preferences_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifiable_notification_preferences');
    }
};
