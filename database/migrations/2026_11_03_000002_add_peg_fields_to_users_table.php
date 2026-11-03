<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->unique()->after('id');
            $table->foreignId('member_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active')->after('password');
            $table->string('theme', 10)->default('light')->after('status');

            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('name');
            $table->dropConstrainedForeignId('member_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'status', 'theme']);
        });
    }
};
