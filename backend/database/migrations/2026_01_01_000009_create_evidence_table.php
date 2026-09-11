<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('check_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 20);
            $table->string('raw_status', 120)->nullable();
            $table->string('title', 255);
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_numeric', 20, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->jsonb('data')->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->string('source', 40);
            $table->timestampTz('collected_at');
            $table->timestampTz('created_at')->nullable();
            $table->string('dedup_key', 64)->nullable();

            $table->unique('dedup_key');
            $table->index(['account_id', 'client_id', 'collected_at']);
            $table->index(['asset_id', 'type', 'collected_at']);
            $table->index(['check_id', 'collected_at']);
            $table->index(['client_id', 'status', 'collected_at']);
            $table->index(['client_id', 'type', 'collected_at']);
        });

        DB::statement("ALTER TABLE evidence ADD CONSTRAINT evidence_status_check CHECK (status IN ('HEALTHY', 'WARNING', 'CRITICAL', 'UNKNOWN', 'FAILED'))");
        DB::statement("ALTER TABLE evidence ADD CONSTRAINT evidence_source_check CHECK (source IN ('check', 'agent', 'webhook', 'manual'))");

        DB::statement('CREATE INDEX evidence_data_gin_index ON evidence USING gin (data)');
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence');
    }
};
