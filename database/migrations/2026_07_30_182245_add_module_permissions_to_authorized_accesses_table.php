<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('authorized_accesses', function (Blueprint $table) {
            $table->boolean('can_operate_ventanilla')->default(true)->after('is_active');
            $table->boolean('can_operate_u300')->default(true)->after('can_operate_ventanilla');
            $table->boolean('can_operate_own_revenue')->default(true)->after('can_operate_u300');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authorized_accesses', function (Blueprint $table) {
            $table->dropColumn(['can_operate_ventanilla', 'can_operate_u300', 'can_operate_own_revenue']);
        });
    }
};
