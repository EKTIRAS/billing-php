<?php

namespace Ektir\Billing\Enums;

enum VatType: string
{
    case Greek = 'greek';
    case EuLocal = 'eu_local';
    case EuReverse = 'eu_reverse';
    case Zero = 'zero';
}
