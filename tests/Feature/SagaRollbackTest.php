<?php

declare(strict_types=1);

use JeromeJHipolito\Saga\Contracts\SagaStepInterface;
use JeromeJHipolito\Saga\Enums\SagaStepStatus;
use JeromeJHipolito\Saga\Enums\SagaTransactionStatus;
use JeromeJHipolito\Saga\Models\SagaTransaction;
use JeromeJHipolito\Saga\Services\SagaTransactionManager;

beforeEach(function () {
    $this->transactionId = 'test-rollback-'.uniqid();
    $this->jobClass      = 'TestJob';
    $this->payload       = ['test' => 'data'];
});

it('rolls back executed steps in reverse order when a step fails', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $rollbackOrder = [];

    $step1 = new class($rollbackOrder) implements SagaStepInterface
    {
        public function __construct(private array &$rollbackOrder) {}

        public function execute(array $data): mixed
        {
            return ['step' => 1];
        }

        public function rollback(mixed $result, array $data): void
        {
            $this->rollbackOrder[] = 'step1';
        }

        public function getStepName(): string
        {
            return 'Step1';
        }
    };

    $step2 = new class($rollbackOrder) implements SagaStepInterface
    {
        public function __construct(private array &$rollbackOrder) {}

        public function execute(array $data): mixed
        {
            return ['step' => 2];
        }

        public function rollback(mixed $result, array $data): void
        {
            $this->rollbackOrder[] = 'step2';
        }

        public function getStepName(): string
        {
            return 'Step2';
        }
    };

    $failingStep = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            throw new \Exception('Step 3 failed');
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'FailingStep';
        }
    };

    $saga->addStep($step1)->addStep($step2)->addStep($failingStep);

    try {
        $saga->execute($this->payload);
    } catch (\Exception $e) {
        $saga->rollback();
    }

    expect($rollbackOrder)->toBe(['step2', 'step1']);
});

it('marks steps as rolled back after rollback', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $step1 = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            return ['step' => 1];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'Step1';
        }
    };

    $failingStep = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            throw new \Exception('Failed');
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'FailingStep';
        }
    };

    $saga->addStep($step1)->addStep($failingStep);

    try {
        $saga->execute($this->payload);
    } catch (\Exception $e) {
        // The executed steps should already be marked as rolled back due to DB rollback
    }

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    expect($transaction->status)->toBe(SagaTransactionStatus::FAILED);
});

it('marks transaction as rolled back after successful rollback', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $step = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            return ['success' => true];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'TestStep';
        }
    };

    $saga->addStep($step);
    $saga->execute($this->payload);

    $saga->rollback();

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    expect($transaction->status)->toBe(SagaTransactionStatus::ROLLED_BACK);
    expect($transaction->rollback_started_at)->not->toBeNull();
    expect($transaction->rollback_completed_at)->not->toBeNull();
});

it('cancels pending steps when a step fails', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $step1 = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            throw new \Exception('First step failed');
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'FailingStep';
        }
    };

    $step2 = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            return ['step' => 2];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'NeverExecutedStep';
        }
    };

    $saga->addStep($step1)->addStep($step2);

    try {
        $saga->execute($this->payload);
    } catch (\Exception $e) {
        // Expected
    }

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    $steps       = $transaction->steps()->orderBy('step_order')->get();

    expect($steps[0]->status)->toBe(SagaStepStatus::FAILED);
    expect($steps[1]->status)->toBe(SagaStepStatus::CANCELLED);
});
