<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_users')) {
            return;
        }

        Schema::table('cms_users', function (Blueprint $table): void {
            if (!Schema::hasColumn('cms_users', 'marketing_email_opt_in')) {
                $table->boolean('marketing_email_opt_in')->default(true)->after('email_verified_at');
            }

            if (!Schema::hasColumn('cms_users', 'marketing_email_opted_out_at')) {
                $table->timestamp('marketing_email_opted_out_at')->nullable()->after('marketing_email_opt_in');
            }

            if (!Schema::hasColumn('cms_users', 'transactional_email_opt_in')) {
                $table->boolean('transactional_email_opt_in')->default(true)->after('marketing_email_opted_out_at');
            }

            if (!Schema::hasColumn('cms_users', 'transactional_email_opted_out_at')) {
                $table->timestamp('transactional_email_opted_out_at')->nullable()->after('transactional_email_opt_in');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_users')) {
            return;
        }

        Schema::table('cms_users', function (Blueprint $table): void {
            foreach ([
                'transactional_email_opted_out_at',
                'transactional_email_opt_in',
                'marketing_email_opted_out_at',
                'marketing_email_opt_in',
            ] as $column) {
                if (Schema::hasColumn('cms_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
