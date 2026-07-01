<?php

namespace App\Models\Pieces;

use App\Enums\Colour;
use App\Traits\SerializeAsPiece;
use Illuminate\Support\Collection;
use JsonSerializable;

class King implements JsonSerializable
{
    use SerializeAsPiece;

    public function __construct(public int $x, public int $y, public Colour $colour, public bool $hasMoved = false) {
        $this->x = $x;
        $this->y = $y;
        $this->colour = $colour;
        $this->hasMoved = $hasMoved;
    }

    public function getSemiLegalMoves(Collection $pieces, bool $includeCastling = true) {
        $possibleMoves = collect();

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1], [1, 1], [1, -1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            $x = $this->x + $dx;
            $y = $this->y + $dy;
            if ($x >= 0 && $x <= 7 && $y >= 0 && $y <= 7) {
                if ($pieces->where('x', $x)->where('y', $y)->where('colour', '=', $this->colour)->isEmpty()) {
                    $possibleMoves->push([$x, $y]);
                }
            }
        }

        if (!$this->hasMoved && $includeCastling) {
            $rooks = $pieces->whereInstanceOf(Rook::class)
                ->where('colour', $this->colour)
                ->where('hasMoved', false);

            foreach ($rooks as $rook) {
                $between = range(min($rook->x, $this->x) + 1, max($rook->x, $this->x) - 1);

                // All squares between rook and king on the same rank must be empty
                if ($pieces->filter(fn($p) => in_array($p->x, $between) && $p->y === $this->y)->isNotEmpty()) {
                    continue;
                }

                $attackedSquares = collect();
                $pieces->where('colour', '!=', $this->colour)
                    ->filter(fn($p) => !($p instanceof King))
                    ->each(fn($piece) => $attackedSquares->push(...$piece->getSemiLegalMoves($pieces)));

                $kingPath = $rook->x < $this->x ? [$this->x - 1, $this->x - 2] : [$this->x + 1, $this->x + 2];
                if ($attackedSquares->contains(fn($sq) => in_array($sq[0], $kingPath) && $sq[1] === $this->y)) {
                    continue;
                }

                $attackedSquares = collect();
                $pieces->where('colour', '!=', $this->colour)
                    ->each(fn($piece) => $attackedSquares->push(...$piece->getSemiLegalMoves($pieces, false)));

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
}
