<?php

namespace App\Models;

use App\Enums\Colour;
use App\Enums\State;

class BoardState {
    public function __construct(
        public State $state = State::ACTIVE,
        public Colour $toMove,
        public string $halfMove,
        public string $fullMove,
    ) {}
}
