<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use JeromeJHipolito\Saga\Enums\SagaTransactionStatus;
use JeromeJHipolito\Saga\Traits\HasUuid;

class SagaTransaction extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'uuid',
        'transaction_id',
        'job_class',
        'payload',
        'status',
        'started_at',
        'completed_at',
        'failed_at',
        'rollback_started_at',
        'rollback_completed_at',
        'error_message',
    ];

    protected $casts = [
        'payload'               => 'array',
        'status'                => SagaTransactionStatus::class,
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
        'failed_at'             => 'datetime',
        'rollback_started_at'   => 'datetime',
        'rollback_completed_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(SagaStep::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === SagaTransactionStatus::COMPLETED;
    }

    public function hasFailed(): bool
    {
        return $this->status === SagaTransactionStatus::FAILED;
    }

    public function isRolledBack(): bool
    {
        return $this->status === SagaTransactionStatus::ROLLED_BACK;
    }

    public function canRollback(): bool
    {
        return in_array($this->status, [SagaTransactionStatus::FAILED, SagaTransactionStatus::COMPLETED])
            && $this->rollback_started_at === null;
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status'     => SagaTransactionStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status'       => SagaTransactionStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status'        => SagaTransactionStatus::FAILED,
            'failed_at'     => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function markRollbackStarted(): void
    {
        $this->update([
            'status'              => SagaTransactionStatus::ROLLING_BACK,
            'rollback_started_at' => now(),
        ]);
    }

    public function markRollbackCompleted(): void
    {
        $this->update([
            'status'                => SagaTransactionStatus::ROLLED_BACK,
            'rollback_completed_at' => now(),
        ]);
    }
}
