<?php

namespace App\Models;

use App\Enums\Colour;
use App\Models\Concerns\HasManyStates;
use App\Models\Pieces\King;
use App\Models\Pieces\Pawn;
use App\Services\FENParser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use function Pest\Laravel\instance;

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

    public const string STARTING_FEN = 'r3k2r/8/8/8/8/8/3P4/R3K2R b KQkq - 0 1';

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
    }

    public function movePiece(int $x, int $y, mixed $piece) {
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
            if ($taken->first()) $this->takePiece($taken->first());
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

        if ($captured) $this->pieces = $this->pieces->push($captured);

        return !$inCheck;
    }

    public function saveFenString(string $fen, string $move = null) {
        $data = FENParser::decodeFenString($fen);

        $this->states()->create(['fen_string' => $fen, 'move' => $move]);

        $this->pieces = $data['pieces'];
        $this->state = $data['state'];
    }

    public function getFenString() {
        return FENParser::encodeFenString($this->pieces, $this->state);
    }

    public function takePiece(mixed $piece) {
        $this->pieces = $this->pieces->reject(fn($p) => $p->x === $piece->x && $p->y === $piece->y);
    }

    public static function toAlgebraic(int $x, int $y): string
    {
        return \chr(\ord('a') + $x) . (8 - $y);
    }
}
