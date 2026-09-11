<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_key', 60);
            $table->string('signature', 190);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['account_id', 'status', 'opened_at']);
            $table->index(['client_id', 'status']);
            $table->index('rule_key');
        });

        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_status_check CHECK (status IN ('open', 'acknowledged', 'resolved', 'ignored'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");

        DB::statement(
            "CREATE UNIQUE INDEX incidents_open_signature_unique
             ON incidents (client_id, signature)
             WHERE status IN ('open', 'acknowledged')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
