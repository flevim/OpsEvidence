<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20)->default('production');
            $table->timestampsTz();

            $table->unique(['client_id', 'name']);
            $table->index(['account_id', 'client_id']);
        });

        DB::statement("ALTER TABLE environments ADD CONSTRAINT environments_type_check CHECK (type IN ('production', 'staging', 'development', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('environments');
    }
};
