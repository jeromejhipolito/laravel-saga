<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga\Enums;

enum SagaStepStatus: string
{
    case PENDING     = 'pending';
    case EXECUTED    = 'executed';
    case FAILED      = 'failed';
    case ROLLED_BACK = 'rolled_back';
    case CANCELLED   = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING     => 'Pending',
            self::EXECUTED    => 'Executed',
            self::FAILED      => 'Failed',
            self::ROLLED_BACK => 'Rolled Back',
            self::CANCELLED   => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return $this === self::PENDING;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::EXECUTED, self::FAILED, self::ROLLED_BACK, self::CANCELLED]);
    }

    public function canRollback(): bool
    {
        return $this === self::EXECUTED;
    }
}
