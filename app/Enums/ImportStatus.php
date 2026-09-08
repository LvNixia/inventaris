<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Preview = 'preview';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Reverted = 'reverted';
}
