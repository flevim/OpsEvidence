<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->jsonb('summary')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['client_id', 'period_start', 'period_end']);
            $table->index(['account_id', 'status', 'period_end']);
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('draft', 'generating', 'ready', 'sent'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
