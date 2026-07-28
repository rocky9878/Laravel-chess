<?php

namespace App\Services;

use App\Models\Objects\Move;
use App\Models\Objects\Position;
use App\Models\Pieces\King;
use App\Models\Pieces\Rook;

final class OpeningBook
{
    private ?PolyglotBook $book = null;

    public function __construct(
        private readonly string $path,
        private readonly PolyglotHasher $hasher = new PolyglotHasher,
    ) {}

    public function findMove(Position $position): ?Move
    {
        if (! is_file($this->path)) {
            return null;
        }

        $this->book ??= new PolyglotBook($this->path);

        $entries = $this->book->findMoves($this->hasher->hash($position));
        if (empty($entries)) {
            return null;
        }

        $chosen = $this->pickWeighted($entries);
        $move = $this->decodeMove($chosen['move'], $position);

        // never trust a book move without confirming it's actually legal
        $piece = $position->pieceAt(...$move->from);
        if ($piece === null) {
            return null;
        }

        $isLegal = array_any(
            $position->legalMovesFor($piece),
            fn ($legalTo) => $legalTo === $move->to
        );

        return $isLegal ? $move : null;
    }

    private function pickWeighted(array $entries): array
    {
        $totalWeight = array_sum(array_map(fn ($e) => max($e['weight'], 1), $entries));
        $roll = random_int(1, $totalWeight);

        foreach ($entries as $entry) {
            $roll -= max($entry['weight'], 1);
            if ($roll <= 0) {
                return $entry;
            }
        }

        return $entries[array_key_last($entries)];
    }

    private function decodeMove(int $encoded, Position $position): Move
    {
        $toFile = $encoded & 0b111;
        $toRank = ($encoded >> 3) & 0b111;
        $fromFile = ($encoded >> 6) & 0b111;
        $fromRank = ($encoded >> 9) & 0b111;
        $promoBits = ($encoded >> 12) & 0b111;

        $from = [$fromFile, 7 - $fromRank];
        $to = [$toFile, 7 - $toRank];

        $promotion = match ($promoBits) {
            1 => 'knight', 2 => 'bishop', 3 => 'rook', 4 => 'queen', default => null,
        };

        // Polyglot encodes castling as the king "capturing" its own rook.
        $piece = $position->pieceAt(...$from);
        if ($piece instanceof King) {
            $target = $position->pieceAt(...$to);
            if ($target instanceof Rook && $target->colour === $piece->colour) {
                $isQueenSide = $to[0] < $from[0];
                $to = [$isQueenSide ? 2 : 6, $from[1]];
            }
        }

        return new Move($from, $to, $promotion);
    }
}
