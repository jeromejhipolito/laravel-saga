<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeromeJHipolito\Saga\Enums\SagaTransactionStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saga_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('transaction_id')->unique();
            $table->string('job_class');
            $table->json('payload');
            $table->enum('status', SagaTransactionStatus::values())
                ->default(SagaTransactionStatus::PENDING->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('rollback_started_at')->nullable();
            $table->timestamp('rollback_completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('job_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saga_transactions');
    }
};
