<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsSensitiveMutationEventsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_sensitive_mutation_events')) {
            return;
        }

        Schema::create('cms_sensitive_mutation_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('source')->nullable();
            $table->string('route_name')->nullable();
            $table->string('request_path')->nullable();
            $table->string('command_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['event_type', 'created_at'], 'cms_sensitive_events_type_created_idx');
            $table->index(['subject_type', 'subject_id'], 'cms_sensitive_events_subject_idx');
            $table->index(['actor_type', 'actor_id'], 'cms_sensitive_events_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_sensitive_mutation_events');
    }
}
