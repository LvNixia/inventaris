<?php

namespace App\Enums;

enum TransactionType: string
{
    case Handover = 'handover';
    case Return = 'return';
    case StatusChange = 'status_change';
    case Disposal = 'disposal';
    case BranchTransfer = 'branch_transfer';
    case Cancellation = 'cancellation';
    case Correction = 'correction';
    case Legacy = 'legacy';
}
