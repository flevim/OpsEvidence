<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('name', 150);
            $table->jsonb('configuration')->nullable();
            $table->unsignedInteger('interval_seconds')->default(600);
            $table->unsignedInteger('freshness_ttl_seconds')->default(1800);
            $table->boolean('enabled')->default(true);
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestampTz('next_run_at')->nullable();
            $table->timestampsTz();

            $table->unique(['asset_id', 'type', 'name']);
            $table->index(['enabled', 'next_run_at']);
            $table->index(['account_id', 'client_id']);
            $table->index(['asset_id', 'enabled']);
        });

        DB::statement(
            "ALTER TABLE checks ADD CONSTRAINT checks_type_check CHECK (type IN (
                'HTTP_STATUS', 'HTTP_RESPONSE_TIME', 'SSL_EXPIRATION', 'SERVER_UPTIME',
                'CPU_USAGE', 'MEMORY_USAGE', 'DISK_USAGE', 'PENDING_UPDATES',
                'DOCKER_CONTAINER_STATUS', 'DOCKER_HEALTH', 'BACKUP_STATUS', 'GITHUB_WORKFLOW',
                'HOST_INFO', 'AGENT_HEARTBEAT'
            ))"
        );

        DB::statement(
            "ALTER TABLE checks ADD CONSTRAINT checks_status_check CHECK (
                last_status IS NULL OR last_status IN ('HEALTHY', 'WARNING', 'CRITICAL', 'UNKNOWN', 'FAILED')
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
