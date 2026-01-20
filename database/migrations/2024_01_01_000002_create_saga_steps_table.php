<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeromeJHipolito\Saga\Enums\SagaStepStatus;
use JeromeJHipolito\Saga\Models\SagaTransaction;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saga_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignIdFor(SagaTransaction::class)->constrained()->cascadeOnDelete();
            $table->string('step_name');
            $table->string('step_class');
            $table->integer('step_order');
            $table->enum('status', SagaStepStatus::values())
                ->default(SagaStepStatus::PENDING->value);
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('rollback_at')->nullable();
            $table->json('result_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['saga_transaction_id', 'step_order']);
            $table->index(['status', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saga_steps');
    }
};
