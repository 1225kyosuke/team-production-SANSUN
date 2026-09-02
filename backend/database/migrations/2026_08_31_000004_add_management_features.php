<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('teacher')->after('password');
            $table->string('kana')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->string('kana')->nullable(); $table->string('gender')->nullable(); $table->date('birth_date')->nullable();
            $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->string('postal_code')->nullable();
            $table->string('address')->nullable(); $table->string('status')->default('在籍中'); $table->string('class_name')->default('1組');
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('course')->default('共通'); $table->string('class_name')->default('1組'); $table->string('deadline')->nullable();
            $table->boolean('is_finalized')->default(false); $table->timestamp('finalized_at')->nullable(); $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps(); $table->unique(['student_id', 'subject_id']);
        });
        Schema::create('api_sessions', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('token_hash', 64)->unique();
            $table->string('user_agent')->nullable(); $table->string('ip_address', 45)->nullable(); $table->timestamp('last_activity_at'); $table->timestamps();
        });
        Schema::create('grade_histories', function (Blueprint $table) {
            $table->id(); $table->foreignId('grade_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); $table->json('before')->nullable(); $table->json('after')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('grade_histories'); Schema::dropIfExists('api_sessions'); Schema::dropIfExists('enrollments');
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn(['teacher_id','course','class_name','deadline','is_finalized','finalized_at','finalized_by']));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['kana','gender','birth_date','email','phone','postal_code','address','status','class_name']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role','kana','gender','is_active']));
    }
};
