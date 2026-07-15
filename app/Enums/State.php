<?php

declare(strict_types=1);

namespace App\Enums;

enum State: string
{
    case ACTIVE = 'active';
    case WHITE = 'white';
    case BLACK = 'black';
    case STALEMATE = 'stalemate';
    case THREEFOLD_REPITION = 'Threefold repition';
    case FIFTY_MOVE_RULE = '50 move rule';
    case INSUFFICIENT_MATERIAL  = 'Insufficient material';
}
