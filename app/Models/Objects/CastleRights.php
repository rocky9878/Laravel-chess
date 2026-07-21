<?php

namespace App\Models\Objects;

use App\Enums\Colour;
use App\Models\Pieces\King;
use App\Models\Pieces\Piece;
use App\Models\Pieces\Rook;

class CastleRights {
    public function __construct(
        readonly bool $whiteKingSide,
        readonly bool $whiteQueenSide,
        readonly bool $blackKingSide,
        readonly bool $blackQueenSide,
    ) {}

    public function after(Piece $piece, ?Piece $captured) {
            return new self(
                whiteKingSide:  $this->whiteKingSide  && !$this->affects($piece, $captured, Colour::WHITE, rookX: 7, homeY: 7),
                whiteQueenSide: $this->whiteQueenSide && !$this->affects($piece, $captured, Colour::WHITE, rookX: 0, homeY: 7),
                blackKingSide:  $this->blackKingSide  && !$this->affects($piece, $captured, Colour::BLACK, rookX: 7, homeY: 0),
                blackQueenSide: $this->blackQueenSide && !$this->affects($piece, $captured, Colour::BLACK, rookX: 0, homeY: 0),
            );
    }

    private function affects(Piece $piece, ?Piece $captured, Colour $colour, int $rookX, int $homeY): bool
    {
        if ($piece instanceof King && $piece->colour === $colour) {
            return true;
        }

        $onRookHomeSquare = fn (Piece $p) => $p instanceof Rook && $p->x === $rookX && $p->y === $homeY;

        return $onRookHomeSquare($piece) || ($captured !== null && $onRookHomeSquare($captured));
    }
}
