<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rule_key', 60);
            $table->boolean('enabled')->default(true);
            $table->jsonb('thresholds')->nullable();
            $table->string('severity', 20)->nullable();
            $table->timestampsTz();

            $table->index(['account_id', 'rule_key']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX rule_settings_account_default_unique
             ON rule_settings (account_id, rule_key)
             WHERE client_id IS NULL"
        );

        DB::statement(
            "CREATE UNIQUE INDEX rule_settings_client_unique
             ON rule_settings (account_id, client_id, rule_key)
             WHERE client_id IS NOT NULL"
        );

        DB::statement("ALTER TABLE rule_settings ADD CONSTRAINT rule_settings_severity_check CHECK (severity IS NULL OR severity IN ('info', 'warning', 'critical'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_settings');
    }
};
