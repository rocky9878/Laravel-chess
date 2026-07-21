<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Models\Objects\Position;
use App\Traits\SerializeAsPiece;
use Illuminate\Support\Collection;
use JsonSerializable;

class King extends Piece implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour) {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
    }

    public function getSemiLegalMoves(Position $position, bool $includeCastling = true): Collection {
        $possibleMoves = collect();

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1], [1, 1], [1, -1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            if ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                $piece = $position->pieceAt($x, $y);
                if (empty($position->pieceAt($x, $y)) || $piece->colour !== $this->colour) {
                    $possibleMoves->push([$x, $y]);
                }
            }
        }

        if ($includeCastling) {
            $kingSideRight = $this->colour === Colour::WHITE ? $position->castling->whiteKingSide : $position->castling->blackKingSide;
            $queenSideRight = $this->colour === Colour::WHITE ? $position->castling->whiteQueenSide : $position->castling->blackQueenSide;

            $rooks = $position->pieces->whereInstanceOf(Rook::class)
                ->where('colour', $this->colour)
                ->filter(fn($rook) => ($rook->x === 0 && $queenSideRight) || ($rook->x === 7 && $kingSideRight));

            foreach ($rooks as $rook) {
                $between = range(min($rook->x, $this->x) + 1, max($rook->x, $this->x) - 1);

                if ($position->pieces->filter(fn($p) => in_array($p->x, $between) && $p->y === $this->y)->isNotEmpty()) {
                    continue;
                }

                $attackedSquares = collect();
                $position->pieces->where('colour', '!=', $this->colour)
                    ->filter(fn($p) => !($p instanceof King))
                    ->each(fn($piece) => $attackedSquares->push(...$piece->getSemiLegalMoves($position)));

                $kingPath = $rook->x < $this->x ? [$this->x - 1, $this->x - 2] : [$this->x + 1, $this->x + 2];
                if ($attackedSquares->contains(fn($sq) => in_array($sq[0], $kingPath) && $sq[1] === $this->y)) {
                    continue;
                }

                $attackedSquares = collect();
                $position->pieces->where('colour', '!=', $this->colour)
                    ->each(fn($piece) => $attackedSquares->push(...$piece->getSemiLegalMoves($position, false)));

                $kingPath = $rook->x < $this->x ? [$this->x - 1, $this->x - 2] : [$this->x + 1, $this->x + 2];
                if ($attackedSquares->contains(fn($sq) => in_array($sq[0], $kingPath) && $sq[1] === $this->y || $sq[0] === $this->x && $sq[1] === $this->y)) {
                    continue;
                }

                $destination = $rook->x < $this->x ? 2 : 6;

                $possibleMoves->push([$destination, $this->y]);
            }
        }

        return $possibleMoves;
    }

    public function withMove(int $x, int $y): static
    {
        return new self($x, $y, $this->colour);
    }
}
