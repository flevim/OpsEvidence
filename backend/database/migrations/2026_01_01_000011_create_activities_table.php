<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestampTz('performed_at');
            $table->boolean('billable')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'performed_at']);
            $table->index(['account_id', 'performed_at']);
        });

        DB::statement(
            "ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN (
                'maintenance', 'upgrade', 'ssl_renewal', 'restore', 'deploy',
                'config_change', 'restart', 'monitoring', 'incident_response', 'other'
            ))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
