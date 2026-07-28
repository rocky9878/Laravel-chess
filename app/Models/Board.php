<?php

namespace App\Models;

use App\Enums\State;
use App\Models\Concerns\HasManyStates;
use App\Models\Objects\Move;
use App\Models\Objects\Position;
use App\Services\FENParser;
use App\Services\OpeningBook;
use App\Services\ZobristHasher;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'white',
    'black',
    'state',
])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
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

    public function piecesForFrontend(): array
    {
        return array_map(fn ($piece) => [
            ...$piece->jsonSerialize(),
            'legalMoves' => $this->position->legalMovesFor($piece),
        ], $this->position->pieces);
    }

    public function makeMove(Move $move)
    {
        $this->position = $this->position->applyMove($move);

        $this->saveState($move->to);
    }

    public function saveState(array $moveDestination)
    {
        $fen = FENParser::encodeFenString($this->position);

        $this->states()->create(['fen_string' => $fen, 'move' => $this->toAlgebraic(...$moveDestination)]);

        $this->setGameState();
    }

    public function setGameState()
    {
        $history = $this->states()->pluck('fen_string');

        $this->state = $this->position->isThreefoldRepetition($history)
            ? State::THREEFOLD_REPITION
            : $this->position->terminalState();

        if ($this->state !== State::ACTIVE) {
            $this->setAttribute('state', $this->state);
            $this->save();
        }
    }

    public static function toAlgebraic(int $x, int $y): string
    {
        return \chr(\ord('a') + $x).(8 - $y);
    }

    public function makeBestMove(int $depth, float $timeLimit)
    {
        if ($this->state !== State::ACTIVE) {
            return;
        }

        $book = new OpeningBook(storage_path('app/books/book.bin'));
        $bookMove = $book->findMove($this->position);

        if ($bookMove !== null) {
            $this->makeMove($bookMove);

            return;
        }

        $hasher = new ZobristHasher;

        $repetitionCounts = [];
        foreach ($this->states()->pluck('fen_string') as $fen) {
            $hash = $hasher->hashPosition(FENParser::decodeFenString($fen));
            $repetitionCounts[$hash] = ($repetitionCounts[$hash] ?? 0) + 1;
        }

        $bestMove = $this->position->iterativeDeepening($depth, $hasher, $timeLimit, $repetitionCounts)[1];

        $this->makeMove($bestMove);
    }
}
