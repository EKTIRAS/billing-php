<?php

namespace Ektir\Billing\Enums;

enum DocumentType: string
{
    case Receipt = 'receipt';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
}
