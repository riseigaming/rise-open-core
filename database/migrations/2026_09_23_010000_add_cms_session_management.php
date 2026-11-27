<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cms_sessions', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_persistent')->default(false);
        });
        Schema::create('cms_session_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('session_record_id')->unique();
            $table->unsignedBigInteger('cms_user_id')->index();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->string('reason', 255);
            $table->string('browser_name')->nullable();
            $table->string('os_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_session_history');
        Schema::table('cms_sessions', function (Blueprint $table): void {
            $table->dropColumn(['expires_at', 'is_persistent']);
        });
    }
};
