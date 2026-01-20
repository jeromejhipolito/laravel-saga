<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Steps;

use JeromeJHipolito\Saga\Contracts\SagaStepInterface;

abstract class AbstractSagaStep implements SagaStepInterface
{
    abstract public function execute(array $data): mixed;

    abstract public function rollback(mixed $result, array $data): void;

    public function getStepName(): string
    {
        return class_basename(static::class);
    }
}
