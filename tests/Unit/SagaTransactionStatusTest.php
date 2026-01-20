<?php

declare(strict_types=1);

use JeromeJHipolito\Saga\Enums\SagaTransactionStatus;

it('has correct values', function () {
    expect(SagaTransactionStatus::values())->toBe([
        'pending',
        'running',
        'completed',
        'failed',
        'rolling_back',
        'rolled_back',
    ]);
});

it('returns correct labels', function () {
    expect(SagaTransactionStatus::PENDING->label())->toBe('Pending');
    expect(SagaTransactionStatus::RUNNING->label())->toBe('Running');
    expect(SagaTransactionStatus::COMPLETED->label())->toBe('Completed');
    expect(SagaTransactionStatus::FAILED->label())->toBe('Failed');
    expect(SagaTransactionStatus::ROLLING_BACK->label())->toBe('Rolling Back');
    expect(SagaTransactionStatus::ROLLED_BACK->label())->toBe('Rolled Back');
});

it('correctly identifies active status', function () {
    expect(SagaTransactionStatus::PENDING->isActive())->toBeTrue();
    expect(SagaTransactionStatus::RUNNING->isActive())->toBeTrue();
    expect(SagaTransactionStatus::ROLLING_BACK->isActive())->toBeTrue();
    expect(SagaTransactionStatus::COMPLETED->isActive())->toBeFalse();
    expect(SagaTransactionStatus::FAILED->isActive())->toBeFalse();
});

it('correctly identifies finished status', function () {
    expect(SagaTransactionStatus::PENDING->isFinished())->toBeFalse();
    expect(SagaTransactionStatus::RUNNING->isFinished())->toBeFalse();
    expect(SagaTransactionStatus::COMPLETED->isFinished())->toBeTrue();
    expect(SagaTransactionStatus::FAILED->isFinished())->toBeTrue();
    expect(SagaTransactionStatus::ROLLED_BACK->isFinished())->toBeTrue();
});

it('correctly identifies rollback eligibility', function () {
    expect(SagaTransactionStatus::COMPLETED->canRollback())->toBeTrue();
    expect(SagaTransactionStatus::FAILED->canRollback())->toBeTrue();
    expect(SagaTransactionStatus::PENDING->canRollback())->toBeFalse();
    expect(SagaTransactionStatus::RUNNING->canRollback())->toBeFalse();
    expect(SagaTransactionStatus::ROLLING_BACK->canRollback())->toBeFalse();
});
