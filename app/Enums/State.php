<?php

declare(strict_types=1);

namespace App\Enums;

enum State: string
{
    case ACTIVE = 'active';
    case WON = 'won';
    case LOST = 'lost';
    case STALEMATE = 'stalemate';
}
