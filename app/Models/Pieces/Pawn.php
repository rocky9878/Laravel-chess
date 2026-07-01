<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Traits\SerializeAsPiece;
use Illuminate\Support\Collection;
use JsonSerializable;

class Pawn implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour, public bool $hasMoved = false, public bool $canBeCapturedEnPassant = false) {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
        $this->hasMoved = $hasMoved;
        $this->canBeCapturedEnPassant = $canBeCapturedEnPassant;
    }

    public function getSemiLegalMoves(Collection $pieces) {
        $possibleMoves = collect();

        $direction = $this->colour === Colour::WHITE ? -1 : 1;

        // basic moves
        $step = [$this->x, $this->y + $direction];
        if($pieces->where('x', $step[0])->where('y', $step[1])->isEmpty()) {
            $possibleMoves->push($step);
            $step = [$this->x, $this->y + $direction * 2];

            if ($pieces->where('x', $step[0])->where('y', $step[1])->isEmpty() && !$this->hasMoved) {
                $possibleMoves->push($step);
            }
        }

        // generate enPassant moves
        $enPassantTarget = $pieces->where('canBeCapturedEnPassant', true)->where('y', $this->y)->filter(fn($piece) => $piece->x + 1 === $this->x || $piece->x - 1 === $this->x)->first();
        if($enPassantTarget) {
            $possibleMoves->push([$enPassantTarget->x, $this->y + $direction]);
        }

        // diagonal taking moves
        foreach ([[1], [-1]] as [$dx]) {
            $x = $this->x + $dx;
            $y = $this->y + $direction;
            if($pieces->where('x', $x)->where('y', $y)->where('colour', '!=', $this->colour)->isNotEmpty()) {
                $possibleMoves->push([$x, $y]);
            }
        }

        return $possibleMoves;
    }
}
