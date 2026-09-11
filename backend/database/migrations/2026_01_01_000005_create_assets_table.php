<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 30);
            $table->string('hostname', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('provider', 80)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('last_evidence_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['client_id', 'name']);
            $table->index(['account_id', 'client_id']);
            $table->index(['client_id', 'type']);
            $table->index(['account_id', 'active']);
        });

        DB::statement(
            "ALTER TABLE assets ADD CONSTRAINT assets_type_check CHECK (type IN (
                'SERVER', 'WEBSITE', 'APPLICATION', 'DATABASE',
                'CONTAINER_HOST', 'REPOSITORY', 'BACKUP_SOURCE', 'OTHER'
            ))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
