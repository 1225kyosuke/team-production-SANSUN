<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('name'); $table->unsignedSmallInteger('year');
            $table->string('term'); $table->unsignedTinyInteger('attendance_weight')->default(50); $table->unsignedTinyInteger('attitude_weight')->default(30); $table->unsignedTinyInteger('assignment_weight')->default(20); $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id(); $table->string('student_number')->unique(); $table->string('name'); $table->string('course'); $table->unsignedTinyInteger('grade_year'); $table->timestamps();
        });
        Schema::create('grades', function (Blueprint $table) {
            $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('attendance_rate', 5, 2)->nullable(); $table->unsignedTinyInteger('attitude')->nullable(); $table->unsignedTinyInteger('assignment')->nullable(); $table->unsignedTinyInteger('score')->nullable(); $table->string('evaluation')->nullable(); $table->timestamps(); $table->unique(['student_id', 'subject_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('grades'); Schema::dropIfExists('students'); Schema::dropIfExists('subjects'); }
};
