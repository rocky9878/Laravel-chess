<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use JsonSerializable;

class Rook extends Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour)
    {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
    }

    public function getSemiLegalMoves(Position $position): array
    {
        $possibleMoves = [];

        // loop through the 4 possible direction;
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            // loop untill a wall is hit or untill a piece is hit and push a possible move
            while ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                $blocking = $position->pieceAt($x, $y);
                if ($blocking) {
                    if ($blocking->colour !== $this->colour) {
                        $possibleMoves[] = [$x, $y];
                    }
                    break;
                }
                $possibleMoves[] = [$x, $y];
                $x += $dx;
                $y += $dy;
            }
        }

        return $possibleMoves;
    }

    public function countSemiLegalMoves(Position $position): int
    {
        $possibleMoves = 0;

        // loop through the 4 possible direction;
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            // loop untill a wall is hit or untill a piece is hit and push a possible move
            while ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                $blocking = $position->pieceAt($x, $y);
                if ($blocking) {
                    if ($blocking->colour !== $this->colour) {
                        $possibleMoves++;
                    }
                    break;
                }
                $possibleMoves++;
                $x += $dx;
                $y += $dy;
            }
        }

        return $possibleMoves;
    }

    public function withMove(int $x, int $y): static
    {
        return new self($x, $y, $this->colour);
    }
}
