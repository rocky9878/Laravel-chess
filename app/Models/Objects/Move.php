<?php

namespace App\Models\Objects;

final class Move
{
    public function __construct(
        public readonly array $from,
        public readonly array $to,
        public readonly ?string $promotion = null,
    ) {}
}
