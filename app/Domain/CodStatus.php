<?php

declare(strict_types=1);

namespace App\Domain;

final class CodStatus
{
    public const NONE = 'none';
    public const PENDING = 'pending';
    public const COLLECTING = 'collecting';
    public const COLLECTED = 'collected';
    public const FAILED = 'failed';
    public const RECONCILED = 'reconciled';
    public const SETTLED = 'settled';
}

