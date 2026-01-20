<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Contracts;

interface SagaTransactionInterface
{
    public function addStep(SagaStepInterface $step): self;

    public function execute(array $data): mixed;

    public function rollback(): void;

    public function getTransactionId(): string;

    public function isCompleted(): bool;

    public function hasFailed(): bool;
}
