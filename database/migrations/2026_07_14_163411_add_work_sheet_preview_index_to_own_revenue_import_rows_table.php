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
        Schema::table('own_revenue_import_rows', function (Blueprint $table) {
            $table->index(
                ['own_revenue_import_file_id', 'row_kind', 'row_number'],
                'own_rev_import_rows_work_sheet_preview_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('own_revenue_import_rows', function (Blueprint $table) {
            $table->dropIndex('own_rev_import_rows_work_sheet_preview_index');
        });
    }
};
