<?php

namespace App\Enums;

enum StockDirection: string
{
    case Out = 'out';
    case In = 'in';
    case Neutral = 'neutral';
    case Writeoff = 'writeoff';
}
