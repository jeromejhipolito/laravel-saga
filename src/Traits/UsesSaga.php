<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Traits;

use Illuminate\Support\Str;
use JeromeJHipolito\Saga\Contracts\SagaTransactionInterface;
use JeromeJHipolito\Saga\Services\SagaTransactionManager;

trait UsesSaga
{
    protected ?SagaTransactionInterface $sagaTransaction = null;

    protected function initializeSaga(array $data): SagaTransactionInterface
    {
        $transactionId = $this->generateTransactionId();

        $this->sagaTransaction = SagaTransactionManager::createTransaction(
            $transactionId,
            static::class,
            $data
        );

        return $this->sagaTransaction;
    }

    protected function executeSaga(array $data): mixed
    {
        if (! $this->sagaTransaction) {
            throw new \RuntimeException('Saga transaction not initialized. Call initializeSaga() first.');
        }

        try {
            return $this->sagaTransaction->execute($data);
        } catch (\Exception $e) {
            $this->rollbackSaga();
            throw $e;
        }
    }

    protected function rollbackSaga(): void
    {
        if ($this->sagaTransaction) {
            $this->sagaTransaction->rollback();
        }
    }

    protected function generateTransactionId(): string
    {
        return static::class.'_'.Str::uuid();
    }

    protected function getSagaTransaction(): ?SagaTransactionInterface
    {
        return $this->sagaTransaction;
    }
}
