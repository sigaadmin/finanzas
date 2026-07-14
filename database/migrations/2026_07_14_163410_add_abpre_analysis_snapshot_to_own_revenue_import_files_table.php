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
        Schema::table('own_revenue_import_files', function (Blueprint $table) {
            $table->foreignId('abpre_import_file_id_at_analysis')
                ->nullable()
                ->after('analysis_revision')
                ->constrained('own_revenue_import_files')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('own_revenue_import_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('abpre_import_file_id_at_analysis');
        });
    }
};
