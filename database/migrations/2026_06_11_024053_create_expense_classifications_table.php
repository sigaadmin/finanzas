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
        Schema::create('expense_classifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('chapter_code', 10);
            $table->string('chapter_name');
            $table->string('concept_code', 10);
            $table->string('concept_name');
            $table->string('generic_item_code', 10);
            $table->string('generic_item_name');
            $table->string('specific_item_code', 10);
            $table->string('specific_item_name');
            $table->string('expense_type_code', 10);
            $table->string('expense_type_name');
            $table->timestamps();

            $table->unique(['fiscal_year', 'specific_item_code']);
            $table->index(['fiscal_year', 'chapter_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_classifications');
    }
};
