<?php

declare(strict_types=1);

use JeromeJHipolito\Saga\Enums\SagaStepStatus;

it('has correct values', function () {
    expect(SagaStepStatus::values())->toBe([
        'pending',
        'executed',
        'failed',
        'rolled_back',
        'cancelled',
    ]);
});

it('returns correct labels', function () {
    expect(SagaStepStatus::PENDING->label())->toBe('Pending');
    expect(SagaStepStatus::EXECUTED->label())->toBe('Executed');
    expect(SagaStepStatus::FAILED->label())->toBe('Failed');
    expect(SagaStepStatus::ROLLED_BACK->label())->toBe('Rolled Back');
    expect(SagaStepStatus::CANCELLED->label())->toBe('Cancelled');
});

it('correctly identifies active status', function () {
    expect(SagaStepStatus::PENDING->isActive())->toBeTrue();
    expect(SagaStepStatus::EXECUTED->isActive())->toBeFalse();
    expect(SagaStepStatus::FAILED->isActive())->toBeFalse();
});

it('correctly identifies finished status', function () {
    expect(SagaStepStatus::PENDING->isFinished())->toBeFalse();
    expect(SagaStepStatus::EXECUTED->isFinished())->toBeTrue();
    expect(SagaStepStatus::FAILED->isFinished())->toBeTrue();
    expect(SagaStepStatus::ROLLED_BACK->isFinished())->toBeTrue();
    expect(SagaStepStatus::CANCELLED->isFinished())->toBeTrue();
});

it('correctly identifies rollback eligibility', function () {
    expect(SagaStepStatus::EXECUTED->canRollback())->toBeTrue();
    expect(SagaStepStatus::PENDING->canRollback())->toBeFalse();
    expect(SagaStepStatus::FAILED->canRollback())->toBeFalse();
});
