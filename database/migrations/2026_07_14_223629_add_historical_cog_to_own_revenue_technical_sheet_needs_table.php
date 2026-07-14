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
        Schema::table('own_revenue_technical_sheet_needs', function (Blueprint $table) {
            $table->foreignId('expense_classification_id')->after('source_row_id')->constrained()->restrictOnDelete();
            $table->string('specific_item_name')->after('specific_item_code');
            $table->string('chapter_code')->after('specific_item_name');
            $table->string('chapter_name')->after('chapter_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('own_revenue_technical_sheet_needs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_classification_id');
            $table->dropColumn(['specific_item_name', 'chapter_code', 'chapter_name']);
        });
    }
};
