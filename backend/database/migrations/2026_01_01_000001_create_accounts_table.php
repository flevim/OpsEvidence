<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('plan', 20)->default('freelancer');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('client_limit')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampsTz();

            $table->index('plan');
            $table->index('status');
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_plan_check CHECK (plan IN ('freelancer', 'msp', 'msp_pro'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_status_check CHECK (status IN ('active', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
