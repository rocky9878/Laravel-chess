<?php

namespace App\Models;

use App\Enums\Colour;
use App\Enums\State;
use App\Models\Concerns\HasManyStates;
use App\Models\Pieces\Bishop;
use App\Models\Pieces\King;
use App\Models\Pieces\Knight;
use App\Models\Pieces\Pawn;
use App\Models\Pieces\Queen;
use App\Models\Pieces\Rook;
use App\Services\FENParser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    public const string STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public BoardState $state;
    public Collection $pieces;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        self::retrieved(function (Board $board) {
            $board->loadBoardState($board->states()->latest()->value('fen_string') ?? self::STARTING_FEN);
        });
        self::created(function (Board $board) {
            $board->loadBoardState(self::STARTING_FEN);
        });
    }

    private function loadBoardState(string $fen): void
    {
        $data = FENParser::decodeFenString($fen);

        $this->pieces = $data['pieces'];
        $this->state = $data['state'];

        $this->setGameState();
    }

    public function movePiece(int $x, int $y, mixed $piece, ?string $promotion) {
        // dd($promotion);
        // reset enPassant and take if played
        if(($enPassantTarget = $this->pieces->where('canBeCapturedEnPassant', true))->isNotEmpty()) {
            $enPassantTarget = $enPassantTarget->first();

            if($piece instanceof Pawn && $x === $enPassantTarget->x && (($piece->colour === Colour::WHITE && $y === $enPassantTarget->y - 1) || ($piece->colour === Colour::BLACK && $y === $enPassantTarget->y + 1))){
                $this->takePiece($enPassantTarget);
            } else {
                $enPassantTarget->canBeCapturedEnPassant = false;
            }
        }

        if($piece instanceof Pawn && (($piece->y === $y + 2 && $piece->colour === Colour::WHITE) || ($piece->y === $y - 2 && $piece->colour === Colour::BLACK))) {
            $piece->canBeCapturedEnPassant = true;
        }

        // update fullmove counter
        if ($piece->colour === Colour::BLACK) {
            $this->state->fullMove += 1;
        }

        // update halfmove counter
        if(($taken = $this->pieces->where('x', $x)->where('y', $y))->isNotEmpty() || $piece instanceof Pawn) {
            $this->state->halfMove = 0;
            if($taken->first()) $this->takePiece($taken->first());
        } else {
            $this->state->halfMove += 1;
        }

        if($this->state->halfMove >= 100) {
            $this->state->state = State::FIFTY_MOVE_RULE;
        }

        // update turn
        $this->state->toMove = $this->state->toMove === Colour::WHITE ? Colour::BLACK : Colour::WHITE;

        // move rook if castled
        if(abs($x - $piece->x) === 2 && $piece instanceof King) {
            $isLeft = $x === 2;
            $this->pieces->where('x', $isLeft ? 0 : 7)->where('y', $piece->colour === Colour::WHITE ? 7 : 0)->first()->x = $isLeft ? 3 : 5;
        }

        $piece->x = $x;
        $piece->y = $y;
        $piece->hasMoved = true;

        if($piece instanceof Pawn && $promotion) {
            $colour = $piece->colour;

            $this->takePiece($this->pieces->where('colour', $colour)->where('x', $piece->x)->where('y', $piece->y)->first());

            $this->pieces->push(match($promotion) {
                'queen' => new Queen($x, $y, $colour),
                'knight' => new Knight($x, $y, $colour),
                'rook' => new Rook($x, $y, $colour),
                'bishop' => new Bishop($x, $y, $colour),
            });
        }

        $this->states()->create(['fen_string' => $this->getFenString(), 'move' => $this->toAlgebraic($x, $y)]);
    }

    public function testIfMoveIsLegal(mixed $piece, array $move) {
        $originalPos = [$piece->x, $piece->y];

        $captured = $this->pieces->where('x', $move[0])->where('y', $move[1])->first();
        if ($captured) $this->pieces = $this->pieces->reject(fn($p) => $p === $captured);

        $piece->x = $move[0];
        $piece->y = $move[1];

        $legalMoves = collect();

        foreach ($this->pieces->where('colour', '!=', $piece->colour) as $testPiece) {
            $legalMoves->push(...$testPiece->getSemiLegalMoves($this->pieces));
        }

        $king = $this->pieces->whereInstanceOf(King::class)->where('colour', $piece->colour)->first();

        $inCheck = $legalMoves->contains(fn($sq) => $sq[0] === $king->x && $sq[1] === $king->y);

        $piece->x = $originalPos[0];
        $piece->y = $originalPos[1];

        if ($captured) $this->pieces = $this->pieces->push($captured)->values();

        return !$inCheck;
    }

    public function saveFenString(string $fen, string $move = null) {
        $data = FENParser::decodeFenString($fen);

        $this->states()->create(['fen_string' => $fen, 'move' => $move]);

        $this->pieces = $data['pieces'];
        $this->state = $data['state'];
    }

    public function setGameState() {
        $this->pieces->each(fn($piece) => $piece->legalMoves = $piece->getSemiLegalMoves($this->pieces));

        if (($whitePieces = $this->pieces->where('colour', Colour::WHITE))->count() <= 2 && ($blackPieces = $this->pieces->where('colour', Colour::BLACK))->count() <= 2){
            if ($whitePieces->whereInstanceOf(Bishop::class)->isNotEmpty() || $whitePieces->whereInstanceOf(Knight::class)->isNotEmpty() || $whitePieces->count() === 1) {
                if ($blackPieces->whereInstanceOf(Bishop::class)->isNotEmpty() || $blackPieces->whereInstanceOf(Knight::class)->isNotEmpty() || $blackPieces->count() === 1) {
                    $this->state->state = State::INSUFFICIENT_MATERIAL;
                }
            }
        }

        $positionKey = FENParser::positionKey($this->getFenString());

        $repetitions = $this->states()
            ->pluck('fen_string')
            ->filter(fn($fen) => FENParser::positionKey($fen) === $positionKey)
            ->count();

        if ($repetitions >= 3) {
            $this->state->state = State::THREEFOLD_REPITION;
        }

        $allMoves = collect();

        $this->pieces->where('colour', $this->state->toMove)->each(function($piece) use ($allMoves) {
            $piece->legalMoves = $piece->legalMoves?->filter(fn($move) => $this->testIfMoveIsLegal($piece, $move))->values();
            $allMoves->push(...$piece->legalMoves);
        });

        $king = $this->pieces->whereInstanceOf(King::class)->where('colour', $this->state->toMove)->first();

        $isInCheck = $this->pieces->where('colour', '!=', $this->state->toMove)->contains(function($piece) use ($king) {
            $moves = $piece->legalMoves?->filter(fn($move) => $this->testIfMoveIsLegal($piece, $move))->values();

            return $moves->contains(fn($sq) => $sq[0] === $king->x && $sq[1] === $king->y);
        });

        if($allMoves->isEmpty()) {
            $this->state->state = $isInCheck
                ? ($this->state->toMove === Colour::WHITE ? State::BLACK : State::WHITE)
                : State::STALEMATE;
        }

        if($this->state->state !== State::ACTIVE) {
            $this->setAttribute('state', $this->state->state);
            $this->save();
        }
    }

    public function getFenString() {
        return FENParser::encodeFenString($this->pieces, $this->state);
    }

    public function takePiece(mixed $piece) {
        $this->pieces = $this->pieces->reject(fn($p) => $p->x === $piece->x && $p->y === $piece->y)->values();
    }

    public static function toAlgebraic(int $x, int $y): string
    {
        return \chr(\ord('a') + $x) . (8 - $y);
    }
}
