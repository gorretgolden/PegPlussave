<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->string('type', 24);
            $table->date('value_date');
            $table->unsignedBigInteger('amount');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('external_loan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->boolean('escalated')->default(false);
            $table->string('status', 20)->default('pending_chair');
            $table->foreignId('maker_id')->constrained('users')->cascadeOnDelete();
            $table->string('maker_role', 20);
            $table->timestamp('made_at');
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['member_id', 'type', 'status']);
            $table->index(['loan_id', 'type', 'status']);
        });

        Schema::create('transaction_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('action', 20);
            $table->text('comment')->nullable();
            $table->timestamp('checked_at');

            $table->index(['transaction_id', 'role']);
        });

        Schema::create('transaction_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('text');
            $table->timestamp('noted_at')->useCurrent();
        });

        // The share each saver was credited on a posting. Kept as rows rather than a blob so a
        // member's interest balance is a sum, and a posting can be shown line by line.
        Schema::create('interest_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('base')->nullable();
            $table->unsignedBigInteger('amount');

            $table->index(['transaction_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_allocations');
        Schema::dropIfExists('transaction_notes');
        Schema::dropIfExists('transaction_checks');
        Schema::dropIfExists('transactions');
    }
};
