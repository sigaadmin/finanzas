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
        Schema::table('u300_requested_items', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_amount_cents')->nullable()->after('total_cents');
            $table->decimal('approved_percentage', 5, 2)->nullable()->after('approved_amount_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('u300_requested_items', function (Blueprint $table) {
            $table->dropColumn(['approved_amount_cents', 'approved_percentage']);
        });
    }
};
