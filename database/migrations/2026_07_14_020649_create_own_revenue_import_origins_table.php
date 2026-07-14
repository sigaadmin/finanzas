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
        Schema::create('own_revenue_import_origins', function (Blueprint $table) {
            $table->id();
            $table->morphs('originable');
            $table->foreignId('own_revenue_import_row_id')->constrained()->cascadeOnDelete();
            $table->string('field_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_import_origins');
    }
};
