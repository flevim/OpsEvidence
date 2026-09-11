<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 120);
            $table->jsonb('configuration')->nullable();
            $table->text('credentials')->nullable();
            $table->string('credentials_reference', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'type', 'name']);
            $table->index(['account_id', 'active']);
        });

        DB::statement(
            "ALTER TABLE integrations ADD CONSTRAINT integrations_type_check CHECK (type IN (
                'github', 'docker', 'uptime_kuma', 'backup_webhook', 'prometheus', 'other'
            ))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
