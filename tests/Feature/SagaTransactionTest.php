<?php

declare(strict_types=1);

use JeromeJHipolito\Saga\Contracts\SagaStepInterface;
use JeromeJHipolito\Saga\Enums\SagaStepStatus;
use JeromeJHipolito\Saga\Enums\SagaTransactionStatus;
use JeromeJHipolito\Saga\Models\SagaTransaction;
use JeromeJHipolito\Saga\Services\SagaTransactionManager;

beforeEach(function () {
    $this->transactionId = 'test-transaction-'.uniqid();
    $this->jobClass      = 'TestJob';
    $this->payload       = ['test' => 'data'];
});

it('creates saga transaction with correct initial state', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    expect($saga)->toBeInstanceOf(SagaTransactionManager::class);
    expect($saga->getTransactionId())->toBe($this->transactionId);
    expect($saga->isCompleted())->toBeFalse();
    expect($saga->hasFailed())->toBeFalse();

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(SagaTransactionStatus::PENDING);
    expect($transaction->job_class)->toBe($this->jobClass);
    expect($transaction->payload)->toBe($this->payload);
});

it('finds saga transaction by transaction id', function () {
    SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $foundSaga = SagaTransactionManager::findByTransactionId($this->transactionId);

    expect($foundSaga)->not->toBeNull();
    expect($foundSaga->getTransactionId())->toBe($this->transactionId);
});

it('returns null when saga transaction not found', function () {
    $foundSaga = SagaTransactionManager::findByTransactionId('non-existent-id');

    expect($foundSaga)->toBeNull();
});

it('executes saga steps successfully', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $mockStep = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            return ['test' => 'result'];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'MockStep';
        }
    };

    $saga->addStep($mockStep);
    $saga->execute($this->payload);

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    expect($transaction->status)->toBe(SagaTransactionStatus::COMPLETED);
});

it('executes multiple saga steps in correct order', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $executionOrder = [];

    $step1 = new class($executionOrder) implements SagaStepInterface
    {
        public function __construct(private array &$executionOrder) {}

        public function execute(array $data): mixed
        {
            $this->executionOrder[] = 'step1';
            return ['step' => 1];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'Step1';
        }
    };

    $step2 = new class($executionOrder) implements SagaStepInterface
    {
        public function __construct(private array &$executionOrder) {}

        public function execute(array $data): mixed
        {
            $this->executionOrder[] = 'step2';
            return ['step' => 2];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'Step2';
        }
    };

    $saga->addStep($step1)->addStep($step2);
    $saga->execute($this->payload);

    expect($executionOrder)->toBe(['step1', 'step2']);

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    $steps       = $transaction->steps()->orderBy('step_order')->get();

    expect($steps)->toHaveCount(2);
    expect($steps[0]->status)->toBe(SagaStepStatus::EXECUTED);
    expect($steps[1]->status)->toBe(SagaStepStatus::EXECUTED);
});

it('marks transaction as completed when all steps succeed', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $mockStep = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            return ['success' => true];
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'SuccessStep';
        }
    };

    $saga->addStep($mockStep);
    $saga->execute($this->payload);

    expect($saga->isCompleted())->toBeTrue();
    expect($saga->hasFailed())->toBeFalse();

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    expect($transaction->status)->toBe(SagaTransactionStatus::COMPLETED);
    expect($transaction->completed_at)->not->toBeNull();
});

it('handles step execution failure and marks transaction as failed', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $failingStep = new class implements SagaStepInterface
    {
        public function execute(array $data): mixed
        {
            throw new \Exception('Step execution failed');
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'FailingStep';
        }
    };

    $saga->addStep($failingStep);

    expect(fn () => $saga->execute($this->payload))->toThrow(\Exception::class, 'Step execution failed');

    expect($saga->hasFailed())->toBeTrue();
    expect($saga->isCompleted())->toBeFalse();

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    expect($transaction->status)->toBe(SagaTransactionStatus::FAILED);
    expect($transaction->failed_at)->not->toBeNull();
    expect($transaction->error_message)->toContain('Step execution failed');
});

it('stores step execution results', function () {
    $saga = SagaTransactionManager::createTransaction(
        $this->transactionId,
        $this->jobClass,
        $this->payload
    );

    $stepResult = ['created_id' => 123, 'was_created' => true];

    $mockStep = new class($stepResult) implements SagaStepInterface
    {
        public function __construct(private array $result) {}

        public function execute(array $data): mixed
        {
            return $this->result;
        }

        public function rollback(mixed $result, array $data): void {}

        public function getStepName(): string
        {
            return 'ResultStep';
        }
    };

    $saga->addStep($mockStep);
    $saga->execute($this->payload);

    $transaction = SagaTransaction::where('transaction_id', $this->transactionId)->first();
    $step        = $transaction->steps->first();

    expect($step->result_data)->toBe($stepResult);
    expect($step->executed_at)->not->toBeNull();
});
