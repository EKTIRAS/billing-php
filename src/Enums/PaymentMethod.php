<?php

namespace Ektir\Billing\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Transfer = 'transfer';
    case Cash = 'cash';
}
