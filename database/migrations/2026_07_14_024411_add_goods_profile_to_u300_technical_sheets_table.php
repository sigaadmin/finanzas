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
        Schema::table('u300_technical_sheets', function (Blueprint $table) {
            $table->json('goods_profile')->nullable()->after('technical_specs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('u300_technical_sheets', function (Blueprint $table) {
            $table->dropColumn('goods_profile');
        });
    }
};
