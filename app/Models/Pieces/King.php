<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use JsonSerializable;

class King extends Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour)
    {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
    }

    public function getSemiLegalMoves(Position $position, bool $includeCastling = true): array
    {
        $possibleMoves = [];

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1], [1, 1], [1, -1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            if ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                $piece = $position->pieceAt($x, $y);
                if (! $piece || $piece->colour !== $this->colour) {
                    $possibleMoves[] = [$x, $y];
                }
            }
        }

        if ($includeCastling) {
            $kingSideRight = $this->colour === Colour::WHITE ? $position->castling->whiteKingSide : $position->castling->blackKingSide;
            $queenSideRight = $this->colour === Colour::WHITE ? $position->castling->whiteQueenSide : $position->castling->blackQueenSide;
            $opponent = $this->colour === Colour::WHITE ? Colour::BLACK : Colour::WHITE;

            foreach ([0, 7] as $rookX) {
                $isQueenSide = $rookX === 0;

                if ($isQueenSide && ! $queenSideRight) {
                    continue;
                }
                if (! $isQueenSide && ! $kingSideRight) {
                    continue;
                }

                $rook = $position->pieceAt($rookX, $this->y);
                if (! ($rook instanceof Rook) || $rook->colour !== $this->colour) {
                    continue;
                }

                $pathClear = true;
                foreach (range(min($rookX, $this->x) + 1, max($rookX, $this->x) - 1) as $bx) {
                    if ($position->pieceAt($bx, $this->y)) {
                        $pathClear = false;
                        break;
                    }
                }
                if (! $pathClear) {
                    continue;
                }

                $kingPath = $isQueenSide ? [$this->x, $this->x - 1, $this->x - 2] : [$this->x, $this->x + 1, $this->x + 2];

                $safe = true;
                foreach ($kingPath as $kx) {
                    if ($position->isSquareAttacked($kx, $this->y, $opponent)) {
                        $safe = false;
                        break;
                    }
                }
                if (! $safe) {
                    continue;
                }

                $destination = $isQueenSide ? 2 : 6;
                $possibleMoves[] = [$destination, $this->y];
            }
        }

        return $possibleMoves;
    }

    public function countSemiLegalMoves(Position $position, bool $includeCastling = true): int
    {
        $possibleMoves = 0;

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1], [1, 1], [1, -1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            if ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                $piece = $position->pieceAt($x, $y);
                if (! $piece || $piece->colour !== $this->colour) {
                    $possibleMoves++;
                }
            }
        }

        if ($includeCastling) {
            $kingSideRight = $this->colour === Colour::WHITE ? $position->castling->whiteKingSide : $position->castling->blackKingSide;
            $queenSideRight = $this->colour === Colour::WHITE ? $position->castling->whiteQueenSide : $position->castling->blackQueenSide;
            $opponent = $this->colour === Colour::WHITE ? Colour::BLACK : Colour::WHITE;

            foreach ([0, 7] as $rookX) {
                $isQueenSide = $rookX === 0;

                if ($isQueenSide && ! $queenSideRight) {
                    continue;
                }
                if (! $isQueenSide && ! $kingSideRight) {
                    continue;
                }

                $rook = $position->pieceAt($rookX, $this->y);
                if (! ($rook instanceof Rook) || $rook->colour !== $this->colour) {
                    continue;
                }

                $pathClear = true;
                foreach (range(min($rookX, $this->x) + 1, max($rookX, $this->x) - 1) as $bx) {
                    if ($position->pieceAt($bx, $this->y)) {
                        $pathClear = false;
                        break;
                    }
                }
                if (! $pathClear) {
                    continue;
                }

                $kingPath = $isQueenSide ? [$this->x, $this->x - 1, $this->x - 2] : [$this->x, $this->x + 1, $this->x + 2];

                $safe = true;
                foreach ($kingPath as $kx) {
                    if ($position->isSquareAttacked($kx, $this->y, $opponent)) {
                        $safe = false;
                        break;
                    }
                }
                if (! $safe) {
                    continue;
                }

                $possibleMoves++;
            }
        }

        return $possibleMoves;
    }

    public function withMove(int $x, int $y): static
    {
        return new self($x, $y, $this->colour);
    }
}
