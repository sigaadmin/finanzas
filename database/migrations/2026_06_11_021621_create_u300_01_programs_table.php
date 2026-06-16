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
        Schema::create('u300_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('name');
            $table->text('objective');
            $table->text('justification');
            $table->unsignedBigInteger('requested_total_cents')->default(0);
            $table->unsignedBigInteger('approved_total_cents')->nullable();
            $table->string('responsible_name');
            $table->string('responsible_position');
            $table->string('responsible_academic_degree');
            $table->string('responsible_phone');
            $table->string('responsible_email');
            $table->string('source_filename')->nullable();
            $table->string('source_path')->nullable();
            $table->timestamps();

            $table->index(['fiscal_year', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_programs');
    }
};
