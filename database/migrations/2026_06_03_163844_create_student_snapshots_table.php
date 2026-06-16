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
        Schema::create('student_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('siga_student_id')->index();
            $table->string('matricula')->nullable()->index();
            $table->string('name');
            $table->string('program')->nullable();
            $table->string('grade')->nullable();
            $table->string('group')->nullable();
            $table->string('academic_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_snapshots');
    }
};
