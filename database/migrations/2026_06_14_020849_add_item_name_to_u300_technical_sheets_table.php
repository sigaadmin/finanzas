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
            $table->text('item_name')->nullable()->after('u300_budget_line_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('u300_technical_sheets', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });
    }
};
