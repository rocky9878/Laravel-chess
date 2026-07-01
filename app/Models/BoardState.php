<?php

namespace App\Models;

use App\Enums\Colour;

class BoardState {
    public function __construct(
        public Colour $toMove,
        public string $halfMove,
        public string $fullMove,
    ) {}
}
