<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Traits\SerializeAsPiece;
use Illuminate\Support\Collection;
use JsonSerializable;

class Bishop implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour) {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
    }

    public function getSemiLegalMoves(Collection $pieces) {
        $possibleMoves = collect();

        // loop through the 8 possible direction;
        foreach([[1, 1], [1, -1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            // loop untill a wall is hit or untill a piece is hit and push a possible move
            while($x <= 7 && $x >= 0 && $y <= 7 && $y >= 0) {
                if (($blocking = $pieces->where('x', $x)->where('y', $y))->isNotEmpty()) {
                    if($blocking->first()->colour !== $this->colour) $possibleMoves->push([$x, $y]);
                    break;
                }
                $possibleMoves->push([$x, $y]);
                $x += $dx;
                $y += $dy;
            }
        }

        return $possibleMoves;
    }
}
