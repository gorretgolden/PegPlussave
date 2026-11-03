<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('department');
            $table->unsignedInteger('monthly_pledge');
            $table->date('joined_on');
            $table->string('extension', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->date('exit_on')->nullable();
            $table->boolean('exit_flagged')->default(false);
            $table->text('exit_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
