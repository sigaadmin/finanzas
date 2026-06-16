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
        Schema::table('u300_programs', function (Blueprint $table) {
            $table->unsignedBigInteger('federal_authorized_total_cents')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('u300_programs', function (Blueprint $table) {
            $table->dropColumn('federal_authorized_total_cents');
        });
    }
};
