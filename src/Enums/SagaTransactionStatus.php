<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Enums;

enum SagaTransactionStatus: string
{
    case PENDING      = 'pending';
    case RUNNING      = 'running';
    case COMPLETED    = 'completed';
    case FAILED       = 'failed';
    case ROLLING_BACK = 'rolling_back';
    case ROLLED_BACK  = 'rolled_back';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING      => 'Pending',
            self::RUNNING      => 'Running',
            self::COMPLETED    => 'Completed',
            self::FAILED       => 'Failed',
            self::ROLLING_BACK => 'Rolling Back',
            self::ROLLED_BACK  => 'Rolled Back',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::RUNNING, self::ROLLING_BACK]);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::ROLLED_BACK]);
    }

    public function canRollback(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED]);
    }
}
