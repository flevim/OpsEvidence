<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['check_id', 'started_at']);
            $table->index(['account_id', 'started_at']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE check_runs ADD CONSTRAINT check_runs_status_check CHECK (status IN ('running', 'success', 'failed', 'partial', 'skipped'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('check_runs');
    }
};
