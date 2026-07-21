<?php

namespace App\Models;

use App\Enums\Colour;
use App\Enums\State;
use App\Models\Concerns\HasManyStates;
use App\Models\Objects\Move;
use App\Models\Objects\Position;
use App\Models\Pieces\Bishop;
use App\Models\Pieces\King;
use App\Models\Pieces\Knight;
use App\Models\Pieces\Pawn;
use App\Models\Pieces\Queen;
use App\Models\Pieces\Rook;
use App\Services\FENParser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use stdClass;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'white',
    'black',
    'state',
])]
class Board extends Model
{
    /** @use HasFactory<\Database\Factories\BoardFactory> */
    use HasFactory;

    /** @use HasManyStates<$this> */
    use HasManyStates;

    public Position $position;

    public State $state;

    public const string STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        self::retrieved(function (Board $board) {
            $board->position = FENParser::decodeFenString($board->states()->latest('id')->value('fen_string') ?? self::STARTING_FEN);
            $board->state = State::from($board->getAttribute('state'));
        });

        self::created(function (Board $board) {
            $board->position = FENParser::decodeFenString(self::STARTING_FEN);
            $board->state = State::ACTIVE;
        });
    }

    public function piecesForFrontend(): Collection
    {
        return $this->position->pieces
            ->map(fn ($piece) => [
                ...$piece->jsonSerialize(),
                'legalMoves' => $this->position->legalMovesFor($piece)?->values(),
            ])->values();
    }

    public function makeMove(array $from, array $to, ?string $promotion) {
        $this->position = $this->position->applyMove(new Move([$from[0], $from[1]], [$to[0], $to[1]], $promotion));

        $this->saveState($to);
    }

    public function saveState(array $move) {
        $fen = FENParser::encodeFenString($this->position);

        $this->states()->create(['fen_string' => $fen, 'move' => $this->toAlgebraic(...$move)]);

        $this->setGameState();
    }

    public function makeBestMove() {
        if ($this->state !== State::ACTIVE) {
            return;
        }

        $piece = $this->piecesForFrontend()->where('colour', Colour::BLACK)->filter(fn($piece) => !$piece['legalMoves']->isEmpty())->random();

        $this->position = $this->position->applyMove(new Move([$piece['x'], $piece['y']], [$piece['legalMoves']->first()[0], $piece['legalMoves']->first()[1]]));

        $this->saveState([$piece['legalMoves']->first()[0], $piece['legalMoves']->first()[1]]);
    }

    public function setGameState() {
        // insufficiant material rule
        if (($whitePieces = $this->position->pieces->where('colour', Colour::WHITE))->count() <= 2 && ($blackPieces = $this->position->pieces->where('colour', Colour::BLACK))->count() <= 2){
            if ($whitePieces->whereInstanceOf(Bishop::class)->isNotEmpty() || $whitePieces->whereInstanceOf(Knight::class)->isNotEmpty() || $whitePieces->count() === 1) {
                if ($blackPieces->whereInstanceOf(Bishop::class)->isNotEmpty() || $blackPieces->whereInstanceOf(Knight::class)->isNotEmpty() || $blackPieces->count() === 1) {
                    $this->state = State::INSUFFICIENT_MATERIAL;
                }
            }
        }

        // 3 fold repition rule
        $positionKey = FENParser::positionKey(FENParser::encodeFenString($this->position));

        $repetitions = $this->states()
            ->pluck('fen_string')
            ->filter(fn($fen) => FENParser::positionKey($fen) === $positionKey)
            ->count();

        if ($repetitions >= 3) {
            $this->state = State::THREEFOLD_REPITION;
        }

        // checkmate/stalemate
        $allMoves = collect();

        $this->position->pieces->where('colour', $this->position->toMove)->each(function($piece) use ($allMoves) {
            $allMoves->push(...$this->position->legalMovesFor($piece));
        });

        $king = $this->position->pieces->whereInstanceOf(King::class)->where('colour', $this->position->toMove)->first();

        $isInCheck = $this->position->pieces->where('colour', '!=', $this->position->toMove)->contains(fn($piece) => $this->position->legalMovesFor($piece)->contains(fn($sq) => $sq[0] === $king->x && $sq[1] === $king->y));

        if($allMoves->isEmpty()) {
            $this->state = $isInCheck
                ? ($this->position->toMove === Colour::WHITE ? State::BLACK : State::WHITE)
                : State::STALEMATE;
        }

        if($this->state !== State::ACTIVE) {
            $this->setAttribute('state', $this->state);
            $this->save();
        }
    }

    public static function toAlgebraic(int $x, int $y): string
    {
        return \chr(\ord('a') + $x) . (8 - $y);
    }

    // public function makeBestMove() {
    //     $origin = $this->getFenString();

    //     $pieces = $this->pieces->where('colour', Colour::BLACK)->filter(fn($piece) => $piece->legalMoves->isNotEmpty())->all();

    //     $minEval = 1000000;
    //     $bestMove = [];
    //     $bestPiece = null;

    //     foreach($pieces as $piece) {
    //         foreach($piece->legalMoves as $move) {
    //             $this->movePiece($move[0], $move[1], $piece, '', false);
    //             $eval = $this->evaluatePosition($this);
    //             if($eval < $minEval) {
    //                 $minEval = $eval;
    //                 $bestMove = $move;
    //                 $bestPiece = $piece;
    //             }
    //             $this->loadBoardState($origin);
    //         }
    //     }

    //     $this->movePiece($bestMove[0], $bestMove[1], $bestPiece);
    // }
}
