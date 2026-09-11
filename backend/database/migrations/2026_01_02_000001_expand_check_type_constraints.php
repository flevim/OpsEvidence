<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplia los valores permitidos de dos restricciones CHECK.
 *
 * Se hace como migracion nueva y no editando las originales: una migracion ya
 * ejecutada es inmutable, y editar el archivo deja las bases existentes con el
 * esquema viejo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE checks DROP CONSTRAINT IF EXISTS checks_type_check');
        DB::statement(
            "ALTER TABLE checks ADD CONSTRAINT checks_type_check CHECK (type IN (
                'HTTP_STATUS', 'HTTP_RESPONSE_TIME', 'SSL_EXPIRATION', 'SERVER_UPTIME',
                'CPU_USAGE', 'MEMORY_USAGE', 'DISK_USAGE', 'PENDING_UPDATES',
                'DOCKER_CONTAINER_STATUS', 'DOCKER_HEALTH', 'BACKUP_STATUS', 'GITHUB_WORKFLOW',
                'HOST_INFO', 'AGENT_HEARTBEAT'
            ))"
        );

        DB::statement('ALTER TABLE check_runs DROP CONSTRAINT IF EXISTS check_runs_status_check');
        DB::statement(
            "ALTER TABLE check_runs ADD CONSTRAINT check_runs_status_check CHECK (
                status IN ('running', 'success', 'failed', 'partial', 'skipped')
            )"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE checks DROP CONSTRAINT IF EXISTS checks_type_check');
        DB::statement(
            "ALTER TABLE checks ADD CONSTRAINT checks_type_check CHECK (type IN (
                'HTTP_STATUS', 'HTTP_RESPONSE_TIME', 'SSL_EXPIRATION', 'SERVER_UPTIME',
                'CPU_USAGE', 'MEMORY_USAGE', 'DISK_USAGE', 'PENDING_UPDATES',
                'DOCKER_CONTAINER_STATUS', 'DOCKER_HEALTH', 'BACKUP_STATUS', 'GITHUB_WORKFLOW'
            ))"
        );

        DB::statement('ALTER TABLE check_runs DROP CONSTRAINT IF EXISTS check_runs_status_check');
        DB::statement(
            "ALTER TABLE check_runs ADD CONSTRAINT check_runs_status_check CHECK (
                status IN ('success', 'failed', 'partial', 'skipped')
            )"
        );
    }
};
