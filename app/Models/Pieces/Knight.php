<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use Illuminate\Support\Collection;
use JsonSerializable;

class Knight extends Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour) {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
    }

    public function getSemiLegalMoves(Position $position): Collection {
        $possibleMoves = collect();

        foreach ([[1, 2], [2, 1], [-1, 2], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            if ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                $blockingPiece = $position->pieceAt($x, $y);

                if(!$blockingPiece || $blockingPiece->colour !== $this->colour) {
                    $possibleMoves->push([$x, $y]);
                }
            }
        }

        return $possibleMoves;
    }

    public function withMove(int $x, int $y): static
    {
        return new self($x, $y, $this->colour);
    }
}
