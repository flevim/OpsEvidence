<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 80);
            $table->text('description')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['account_id', 'slug']);
            $table->index(['account_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
