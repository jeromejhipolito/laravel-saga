<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Contracts;

interface SagaStepInterface
{
    public function execute(array $data): mixed;

    public function rollback(mixed $result, array $data): void;

    public function getStepName(): string;
}
