<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use JeromeJHipolito\Saga\Enums\SagaStepStatus;
use JeromeJHipolito\Saga\Traits\HasUuid;

class SagaStep extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'uuid',
        'saga_transaction_id',
        'step_name',
        'step_class',
        'step_order',
        'status',
        'executed_at',
        'rollback_at',
        'result_data',
        'error_message',
    ];

    protected $casts = [
        'result_data' => 'array',
        'status'      => SagaStepStatus::class,
        'executed_at' => 'datetime',
        'rollback_at' => 'datetime',
        'step_order'  => 'integer',
    ];

    public function sagaTransaction(): BelongsTo
    {
        return $this->belongsTo(SagaTransaction::class);
    }

    public function isExecuted(): bool
    {
        return $this->status === SagaStepStatus::EXECUTED;
    }

    public function isRolledBack(): bool
    {
        return $this->status === SagaStepStatus::ROLLED_BACK;
    }

    public function canRollback(): bool
    {
        return $this->status === SagaStepStatus::EXECUTED && $this->rollback_at === null;
    }

    public function markAsExecuted(mixed $result): void
    {
        DB::transaction(function () use ($result) {
            $this->update([
                'status'      => SagaStepStatus::EXECUTED,
                'executed_at' => now(),
                'result_data' => is_array($result) ? $result : ['result' => $result],
            ]);
        });
    }

    public function markAsRolledBack(): void
    {
        DB::transaction(function () {
            $this->update([
                'status'      => SagaStepStatus::ROLLED_BACK,
                'rollback_at' => now(),
            ]);
        });
    }

    public function markAsFailed(string $errorMessage): void
    {
        DB::transaction(function () use ($errorMessage) {
            $this->update([
                'status'        => SagaStepStatus::FAILED,
                'error_message' => $errorMessage,
            ]);
        });
    }
}
