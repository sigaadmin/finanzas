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
        Schema::create('own_revenue_import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_import_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_row_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('severity');
            $table->string('code');
            $table->string('field')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['own_revenue_import_file_id', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_import_issues');
    }
};
