<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedTinyInteger('months');
            $table->decimal('rate', 5, 2);
            $table->string('method', 12)->default('flat');
            $table->string('purpose');
            $table->string('status', 20)->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->date('disbursed_on')->nullable();
            $table->foreignId('appraised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('appraised_at')->nullable();
            $table->string('appraisal_decision', 20)->nullable();
            $table->text('appraisal_note')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });

        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();

            $table->unique(['loan_id', 'member_id']);
        });

        Schema::create('external_loans', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();
            $table->string('lender');
            $table->unsignedBigInteger('amount');
            $table->decimal('rate', 5, 2);
            $table->unsignedTinyInteger('months');
            $table->string('purpose')->nullable();
            $table->string('status', 20)->default('in_approval');
            $table->date('received_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_loans');
        Schema::dropIfExists('loan_guarantors');
        Schema::dropIfExists('loans');
    }
};
