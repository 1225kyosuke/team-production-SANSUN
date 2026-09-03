<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_set_password')->default(false);
            $table->string('password_setup_token_hash', 64)->nullable()->index();
            $table->timestamp('password_setup_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_set_password', 'password_setup_token_hash', 'password_setup_expires_at']);
        });
    }
};
