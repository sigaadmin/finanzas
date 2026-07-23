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
        Schema::create('u300_backup_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('u300_backup_archive_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('type', 32);
            $table->string('status', 16);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fiscal_year', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_backup_operations');
    }
};
