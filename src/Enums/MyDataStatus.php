<?php

namespace Ektir\Billing\Enums;

enum MyDataStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Failed = 'failed';
    case Offline = 'offline';

    public function isFinal(): bool
    {
        return $this === self::Submitted || $this === self::Failed;
    }
}
