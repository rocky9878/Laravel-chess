<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use Illuminate\Support\Collection;
use JsonSerializable;

class Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public int $x;
    public int $y;
    public Colour $colour;

    public function getSemiLegalMoves(Position $position): Collection {return collect();}

    public function withMove(int $x, int $y): static { return new self; }

    public function withPromotion(int $x, int $y, string $pieceType) {
        return match($pieceType) {
            'queen' => new Queen($x, $y, $this->colour),
            'rook' => new Rook($x, $y, $this->colour),
            'bishop' => new Bishop($x, $y, $this->colour),
            'knight' => new Knight($x, $y, $this->colour),
        };
    }
}
