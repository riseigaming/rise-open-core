<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_user_id')->constrained('cms_users')->cascadeOnDelete();
            $table->boolean('active')->default(true)->index();
            $table->string('title')->nullable();
            $table->string('department')->nullable();
            $table->timestamps();

            $table->unique('cms_user_id', 'cms_staff_profiles_user_unique');
        });

        Schema::create('cms_staff_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_user_id')->constrained('cms_users')->cascadeOnDelete();
            $table->string('permission_key', 120);
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();

            $table->unique(['cms_user_id', 'permission_key'], 'cms_staff_permissions_user_key_unique');
            $table->index('permission_key', 'cms_staff_permissions_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_staff_permissions');
        Schema::dropIfExists('cms_staff_profiles');
    }
};
