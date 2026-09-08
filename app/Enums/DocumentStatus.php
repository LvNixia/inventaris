<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';
}
