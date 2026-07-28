<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use JsonSerializable;
use ReflectionClass;

class Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public int $x;

    public int $y;

    public Colour $colour;

    public function getSemiLegalMoves(Position $position): array
    {
        return [];
    }

    public function countSemiLegalMoves(Position $position): int
    {
        return 0;
    }

    public function withMove(int $x, int $y): static
    {
        return new self;
    }

    public function withPromotion(int $x, int $y, string $pieceType)
    {
        return match ($pieceType) {
            'queen' => new Queen($x, $y, $this->colour),
            'rook' => new Rook($x, $y, $this->colour),
            'bishop' => new Bishop($x, $y, $this->colour),
            'knight' => new Knight($x, $y, $this->colour),
        };
    }

    public function getClassChar()
    {
        $ref = new ReflectionClass($this);
        if ($this instanceof Knight) {
            return $this->colour === Colour::BLACK ? 'n' : 'N';
        }

        return $this->colour === Colour::BLACK ? strtolower($ref->getShortName()[0]) : strtoupper($ref->getShortName()[0]);
    }
}
