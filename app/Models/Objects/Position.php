<?php

namespace App\Models\Objects;

use App\Enums\Colour;
use App\Enums\State;
use App\Models\Board;
use App\Models\Pieces\Bishop;
use App\Models\Pieces\King;
use App\Models\Pieces\Knight;
use App\Models\Pieces\Pawn;
use App\Models\Pieces\Piece;
use App\Models\Pieces\Queen;
use App\Models\Pieces\Rook;
use Illuminate\Support\Collection;

final class Position
{
    private readonly array $index; // "x,y" => Piece

    public function __construct(
        public readonly Collection $pieces,       // Collection<Piece>, itself immutable
        public readonly Colour $toMove,
        public readonly CastleRights $castling,  // named booleans, not FEN letters
        public readonly ?array $enPassantTarget,   // [x, y] or null
        public readonly int $halfMove,
        public readonly int $fullMove,
    ) {
        $index = [];
        foreach ($pieces as $piece) {
            $index["{$piece->x},{$piece->y}"] = $piece;
        }
        $this->index = $index;
    }

    public function applyMove(Move $move): Position {
        $piece = $this->pieceAt(...$move->from);
        $captured = $this->pieceAt(...$move->to);
        $castleTarget = null;
        $enPassantVictim = null;

        if ($piece instanceof Pawn && $this->enPassantTarget && $move->to === $this->enPassantTarget) {
            $enPassantVictim = $this->pieceAt(...(
                $piece->colour === Colour::WHITE ? [$this->enPassantTarget[0], $this->enPassantTarget[1] + 1] : [$this->enPassantTarget[0], $this->enPassantTarget[1] - 1]
            ));
        }

        if($piece instanceOf King && abs($move->from[0] - $move->to[0]) === 2){
            $castleTarget = $this->pieceAt($move->to[0] === 2 ? 0 : 7, $move->to[1]);
        }

        $newPieces = $this->pieces->filter(fn($p) => $p !== $captured && $p !== $piece && $p !== $enPassantVictim && $p !== $castleTarget);

        $enPassantTarget = null;

        if($piece instanceOf Pawn && abs($move->from[1] - $move->to[1]) === 2) {
            $enPassantTarget = ($piece->colour === Colour::WHITE ? [$move->from[0], $move->from[1] - 1] : [$move->from[0], $move->from[1] + 1]);
        }

        if ($castleTarget) {
            $newPieces->push($castleTarget->withMove($move->to[0] === 2 ? 3 : 5, $piece->y));
        }

        $newPieces->push($move->promotion ?
            $piece->withPromotion($move->to[0], $move->to[1], $move->promotion) :
            $piece->withMove(...$move->to));

        $halfMove = $piece instanceOf Pawn || isset($captured) ? 0 : $this->halfMove + 1;

        return new Position(
            $newPieces->values(),
            $this->toMove === Colour::BLACK ? Colour::WHITE : Colour::BLACK,
            $this->castling->after($piece, $captured),
            $enPassantTarget,
            $halfMove,
            $this->toMove === Colour::BLACK ? $this->fullMove + 1 : $this->fullMove
        );
    }

    public function pieceAt(int $x, int $y): ?Piece
    {
        return $this->index["$x,$y"] ?? null;
    }

    public function legalMovesFor(Piece $piece) {
        $moves = $piece->getSemiLegalMoves($this);

        return $moves->filter(function($move) use ($piece) {
            $position = $this->applyMove(new Move([$piece->x, $piece->y], $move));
            $king = $position->pieces->whereInstanceOf(King::class)->where('colour', $piece->colour)->first();
            return $position->pieces->where('colour', '!=', $king->colour)->flatmap(fn ($p) => $p->getSemiLegalMoves($position, false)->where('0', $king->x)->where('1', $king->y))->isEmpty();
        });
    }

    public function evaluatePosition()
    {
        $score = 0;

        // material score
        $score += $this->pieces->sum(fn ($piece) =>
            match (true) {
                $piece instanceOf Pawn => 100,
                $piece instanceOf Knight => 350,
                $piece instanceOf Bishop => 350,
                $piece instanceOf Rook => 525,
                $piece instanceOf Queen => 1000,
                $piece instanceOf King => 10000,
            } * ($piece->colour === Colour::WHITE ? 1 : -1)
        );

        // mobility score
        $score += $this->pieces->where('colour', Colour::WHITE)->sum(fn($piece) => $this->legalMovesFor($piece)->count() * 2);
        $score += $this->pieces->where('colour', Colour::BLACK)->sum(fn($piece) => $this->legalMovesFor($piece)->count() * -2);

        // stacked pawns
        $score += $this->pieces
            ->where('colour', Colour::WHITE)
            ->whereInstanceOf(Pawn::class)
            ->groupBy('x')
            ->filter(fn($pawnsOnFile) => $pawnsOnFile->count() > 1)->count() * -1;

        $score += $this->pieces
            ->where('colour', Colour::BLACK)
            ->whereInstanceOf(Pawn::class)
            ->groupBy('x')
            ->filter(fn($pawnsOnFile) => $pawnsOnFile->count() > 1)->count();

        return $score;
    }
}


