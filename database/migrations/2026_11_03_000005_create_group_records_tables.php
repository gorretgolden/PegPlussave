<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->date('date');
            $table->string('title');
            $table->string('type', 20);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->string('title');
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->date('posted_on');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('author_role', 20);
            $table->timestamps();
        });

        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->string('title');
            $table->date('date');
            $table->string('kind', 20);
            $table->string('attendance')->nullable();
            $table->text('summary');
            $table->json('decisions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->string('name');
            $table->string('department');
            $table->string('stage', 20)->default('Approached');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'month']);
        });

        Schema::create('payment_notices', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('channel', 30);
            $table->string('reference')->nullable();
            $table->string('month', 7);
            $table->string('status', 20)->default('open');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notices');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('prospects');
        Schema::dropIfExists('minutes');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('events');
    }
};
