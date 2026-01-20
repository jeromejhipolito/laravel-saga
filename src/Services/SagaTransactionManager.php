<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JeromeJHipolito\Saga\Contracts\SagaStepInterface;
use JeromeJHipolito\Saga\Contracts\SagaTransactionInterface;
use JeromeJHipolito\Saga\Enums\SagaStepStatus;
use JeromeJHipolito\Saga\Enums\SagaTransactionStatus;
use JeromeJHipolito\Saga\Models\SagaStep;
use JeromeJHipolito\Saga\Models\SagaTransaction;

class SagaTransactionManager implements SagaTransactionInterface
{
    protected SagaTransaction $transaction;

    protected array $steps = [];

    protected array $executedSteps = [];

    public function __construct(?SagaTransaction $transaction = null)
    {
        if ($transaction) {
            $this->transaction = $transaction;
            $this->loadExistingSteps();
        }
    }

    public static function createTransaction(string $transactionId, string $jobClass, array $payload): self
    {
        $transaction = SagaTransaction::create([
            'uuid'           => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'job_class'      => $jobClass,
            'payload'        => $payload,
            'status'         => SagaTransactionStatus::PENDING,
        ]);

        return new self($transaction);
    }

    public static function findByTransactionId(string $transactionId): ?self
    {
        $transaction = SagaTransaction::where('transaction_id', $transactionId)->first();

        return $transaction ? new self($transaction) : null;
    }

    public function addStep(SagaStepInterface $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    public function execute(array $data): mixed
    {
        $this->transaction->markAsStarted();

        $stepModels = [];
        foreach ($this->steps as $index => $step) {
            $stepModels[$index] = $this->createStepModel($step, $index);
        }

        DB::beginTransaction();

        try {
            $lastResult = null;

            foreach ($this->steps as $index => $step) {
                $stepModel = $stepModels[$index];

                try {
                    $result = $step->execute($data);
                    $stepModel->markAsExecuted($result);

                    $this->executedSteps[] = [
                        'step'   => $step,
                        'model'  => $stepModel,
                        'result' => $result,
                    ];

                    $lastResult = $result;

                    Log::info('Saga step executed successfully', [
                        'transaction_id' => $this->transaction->transaction_id,
                        'step_name'      => $step->getStepName(),
                        'step_order'     => $index,
                    ]);

                } catch (\Exception $e) {
                    $stepModel->markAsFailed($e->getMessage());
                    throw $e;
                }
            }

            $this->transaction->markAsCompleted();
            DB::commit();

            Log::info('Saga transaction completed successfully', [
                'transaction_id' => $this->transaction->transaction_id,
                'steps_count'    => count($this->steps),
            ]);

            return $lastResult;

        } catch (\Exception $e) {
            DB::rollBack();

            DB::transaction(function () use ($e) {
                $this->transaction->markAsFailed($e->getMessage());
                $this->markExecutedStepsAsRolledBack();

                $pendingSteps = SagaStep::where('saga_transaction_id', $this->transaction->id)
                    ->where('status', SagaStepStatus::PENDING)
                    ->orderBy('step_order')
                    ->get();

                if ($pendingSteps->isNotEmpty()) {
                    $failingStep = $pendingSteps->first();
                    $failingStep->update([
                        'status'        => SagaStepStatus::FAILED,
                        'error_message' => $e->getMessage(),
                    ]);

                    $remainingSteps = $pendingSteps->slice(1);
                    foreach ($remainingSteps as $step) {
                        $step->update([
                            'status' => SagaStepStatus::CANCELLED,
                        ]);
                    }
                }
            });

            Log::error('Saga transaction failed', [
                'transaction_id' => $this->transaction->transaction_id,
                'error'          => $e->getMessage(),
                'executed_steps' => count($this->executedSteps),
            ]);

            throw $e;
        }
    }

    public function rollback(): void
    {
        if (! $this->transaction->canRollback()) {
            Log::warning('Cannot rollback saga transaction', [
                'transaction_id' => $this->transaction->transaction_id,
                'status'         => $this->transaction->status,
            ]);

            return;
        }

        $this->transaction->markRollbackStarted();

        DB::beginTransaction();

        try {
            $stepsToRollback = $this->getExecutedStepsInReverseOrder();

            foreach ($stepsToRollback as $stepData) {
                $step      = $stepData['step'];
                $stepModel = $stepData['model'];
                $result    = $stepData['result'];

                try {
                    $step->rollback($result, $this->transaction->payload);
                    $stepModel->markAsRolledBack();

                    Log::info('Saga step rolled back successfully', [
                        'transaction_id' => $this->transaction->transaction_id,
                        'step_name'      => $step->getStepName(),
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to rollback saga step', [
                        'transaction_id' => $this->transaction->transaction_id,
                        'step_name'      => $step->getStepName(),
                        'error'          => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }

            $this->transaction->markRollbackCompleted();
            DB::commit();

            Log::info('Saga transaction rolled back successfully', [
                'transaction_id' => $this->transaction->transaction_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Saga rollback failed', [
                'transaction_id' => $this->transaction->transaction_id,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getTransactionId(): string
    {
        return $this->transaction->transaction_id;
    }

    public function isCompleted(): bool
    {
        return $this->transaction->isCompleted();
    }

    public function hasFailed(): bool
    {
        return $this->transaction->hasFailed();
    }

    protected function createStepModel(SagaStepInterface $step, int $order): SagaStep
    {
        return DB::transaction(function () use ($step, $order) {
            return SagaStep::create([
                'uuid'                => (string) Str::uuid(),
                'saga_transaction_id' => $this->transaction->id,
                'step_name'           => $step->getStepName(),
                'step_class'          => get_class($step),
                'step_order'          => $order,
                'status'              => SagaStepStatus::PENDING,
            ]);
        });
    }

    protected function loadExistingSteps(): void
    {
        $stepModels = $this->transaction->steps()
            ->where('status', SagaStepStatus::EXECUTED)
            ->orderBy('step_order')
            ->get();

        foreach ($stepModels as $stepModel) {
            if (class_exists($stepModel->step_class)) {
                $step = app($stepModel->step_class);

                $this->executedSteps[] = [
                    'step'   => $step,
                    'model'  => $stepModel,
                    'result' => $stepModel->result_data,
                ];
            }
        }
    }

    protected function getExecutedStepsInReverseOrder(): array
    {
        return array_reverse($this->executedSteps);
    }

    protected function markExecutedStepsAsRolledBack(): void
    {
        foreach ($this->executedSteps as $stepData) {
            $stepModel = $stepData['model'];
            if ($stepModel->status === SagaStepStatus::EXECUTED) {
                $stepModel->update([
                    'status'      => SagaStepStatus::ROLLED_BACK,
                    'rollback_at' => now(),
                ]);
            }
        }
    }
}
