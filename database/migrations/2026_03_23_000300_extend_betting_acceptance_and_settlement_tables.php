<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendBettingAcceptanceAndSettlementTables extends Migration
{
    public function up()
    {
        Schema::table('betting_acceptances', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_cms_user_id')->nullable()->after('root_user_id');
            $table->dateTime('reviewed_at')->nullable()->after('accepted_at');
            $table->dateTime('last_transition_at')->nullable()->after('reviewed_at');
            $table->string('decision_source', 30)->default('system')->after('decision');
            $table->string('decision_reason', 191)->nullable()->after('rejected_reason');
            $table->json('override_meta')->nullable()->after('context');

            $table->index(['status', 'decision'], 'betting_acceptances_status_decision_idx');
            $table->index(['reviewed_by_cms_user_id'], 'betting_acceptances_reviewed_by_idx');
        });

        Schema::table('betting_settlements', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_cms_user_id')->nullable()->after('betting_acceptance_id');
            $table->dateTime('reviewed_at')->nullable()->after('settled_at');
            $table->string('decision_source', 30)->default('system')->after('status');
            $table->string('review_notes', 191)->nullable()->after('reason');
            $table->json('override_meta')->nullable()->after('context');

            $table->index(['status', 'result_code'], 'betting_settlements_status_result_review_idx');
            $table->index(['reviewed_by_cms_user_id'], 'betting_settlements_reviewed_by_idx');
        });

        Schema::create('betting_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('betting_event_id')->nullable();
            $table->unsignedBigInteger('betting_acceptance_id')->nullable();
            $table->unsignedBigInteger('betting_settlement_id')->nullable();
            $table->unsignedBigInteger('cms_user_id')->nullable();
            $table->string('action', 50);
            $table->string('level', 20)->default('info');
            $table->string('message', 191);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'betting_activity_logs_entity_idx');
            $table->index(['betting_acceptance_id'], 'betting_activity_logs_acceptance_idx');
            $table->index(['betting_settlement_id'], 'betting_activity_logs_settlement_idx');
            $table->index(['betting_event_id', 'created_at'], 'betting_activity_logs_event_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('betting_activity_logs');

        Schema::table('betting_settlements', function (Blueprint $table) {
            $table->dropIndex('betting_settlements_status_result_review_idx');
            $table->dropIndex('betting_settlements_reviewed_by_idx');
            $table->dropColumn([
                'reviewed_by_cms_user_id',
                'reviewed_at',
                'decision_source',
                'review_notes',
                'override_meta',
            ]);
        });

        Schema::table('betting_acceptances', function (Blueprint $table) {
            $table->dropIndex('betting_acceptances_status_decision_idx');
            $table->dropIndex('betting_acceptances_reviewed_by_idx');
            $table->dropColumn([
                'reviewed_by_cms_user_id',
                'reviewed_at',
                'last_transition_at',
                'decision_source',
                'decision_reason',
                'override_meta',
            ]);
        });
    }
}
