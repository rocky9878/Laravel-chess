<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use JsonSerializable;

class Pawn extends Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour, public bool $hasMoved = false)
    {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
        $this->hasMoved = $hasMoved;
    }

    public function getSemiLegalMoves(Position $position): array
    {
        $possibleMoves = [];

        $direction = $this->colour === Colour::WHITE ? -1 : 1;

        // basic moves
        $step = [$this->x, $this->y + $direction];
        if (! $position->pieceAt($step[0], $step[1])) {
            $possibleMoves[] = $step;
            $step = [$this->x, $this->y + $direction * 2];

            if (! $position->pieceAt($step[0], $step[1]) && ! $this->hasMoved) {
                $possibleMoves[] = $step;
            }
        }

        // generate enPassant moves
        if ($position->enPassantTarget) {
            $enPassantTarget = $position->pieceAt($position->enPassantTarget[0], ($this->colour === Colour::WHITE ? $position->enPassantTarget[1] + 1 : $position->enPassantTarget[1] - 1));
            if ($enPassantTarget && $enPassantTarget->y === $this->y && abs($enPassantTarget->x - $this->x) === 1) {
                $possibleMoves[] = [$enPassantTarget->x, $this->y + $direction];
            }
        }

        // diagonal taking moves
        foreach ([[1], [-1]] as [$dx]) {
            $x = $this->x + $dx;
            $y = $this->y + $direction;
            $piece = $position->pieceAt($x, $y);
            if ($piece && $piece->colour !== $this->colour) {
                $possibleMoves[] = [$x, $y];
            }
        }

        return $possibleMoves;
    }

    public function countSemiLegalMoves(Position $position): int
    {
        $possibleMoves = 0;

        $direction = $this->colour === Colour::WHITE ? -1 : 1;

        // basic moves
        if (! $position->pieceAt($this->x, $this->y + $direction)) {
            $possibleMoves++;

            if (! $position->pieceAt($this->x, $this->y + $direction * 2) && ! $this->hasMoved) {
                $possibleMoves++;
            }
        }

        // generate enPassant moves
        if ($position->enPassantTarget) {
            $enPassantTarget = $position->pieceAt($position->enPassantTarget[0], ($this->colour === Colour::WHITE ? $position->enPassantTarget[1] + 1 : $position->enPassantTarget[1] - 1));
            if ($enPassantTarget && $enPassantTarget->y === $this->y && abs($enPassantTarget->x - $this->x) === 1) {
                $possibleMoves++;
            }
        }

        // diagonal taking moves
        foreach ([[1], [-1]] as [$dx]) {
            $piece = $position->pieceAt($this->x + $dx, $this->y + $direction);
            if ($piece && $piece->colour !== $this->colour) {
                $possibleMoves++;
            }
        }

        return $possibleMoves;
    }

    public function withMove(int $x, int $y): static
    {
        return new self($x, $y, $this->colour, hasMoved: true);
    }
}
