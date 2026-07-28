<?php

namespace App\Models\Objects;

use App\Enums\Colour;
use App\Enums\State;
use App\Models\Pieces\Bishop;
use App\Models\Pieces\King;
use App\Models\Pieces\Knight;
use App\Models\Pieces\Pawn;
use App\Models\Pieces\Piece;
use App\Models\Pieces\Queen;
use App\Models\Pieces\Rook;
use App\Services\FENParser;
use App\Services\ZobristHasher;
use Illuminate\Support\Collection;

final class Position
{
    private readonly array $index; // "x,y" => Piece

    private ?King $whiteKing = null;

    private ?King $blackKing = null;

    public function __construct(
        public readonly array $pieces,       // array<Piece>, itself immutable
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

    public function applyMove(Move $move): Position
    {
        $piece = $this->pieceAt(...$move->from);
        $captured = $this->pieceAt(...$move->to);
        $castleTarget = null;
        $enPassantVictim = null;

        if ($piece instanceof Pawn && $this->enPassantTarget && $move->to === $this->enPassantTarget) {
            $enPassantVictim = $this->pieceAt(...(
                $piece->colour === Colour::WHITE ? [$this->enPassantTarget[0], $this->enPassantTarget[1] + 1] : [$this->enPassantTarget[0], $this->enPassantTarget[1] - 1]
            ));
        }

        if ($piece instanceof King && abs($move->from[0] - $move->to[0]) === 2) {
            $castleTarget = $this->pieceAt($move->to[0] === 2 ? 0 : 7, $move->to[1]);
        }

        $newPieces = array_values(array_filter(
            $this->pieces,
            fn ($p) => $p !== $captured && $p !== $piece && $p !== $enPassantVictim && $p !== $castleTarget
        ));

        $enPassantTarget = null;

        if ($piece instanceof Pawn && abs($move->from[1] - $move->to[1]) === 2) {
            $enPassantTarget = ($piece->colour === Colour::WHITE ? [$move->from[0], $move->from[1] - 1] : [$move->from[0], $move->from[1] + 1]);
        }

        if ($castleTarget) {
            $newPieces[] = $castleTarget->withMove($move->to[0] === 2 ? 3 : 5, $piece->y);
        }

        $newPieces[] = $move->promotion && $piece instanceof Pawn && ($move->to[1] === 0 || $move->to[1] === 7) ?
            $piece->withPromotion($move->to[0], $move->to[1], $move->promotion) :
            $piece->withMove(...$move->to);

        $halfMove = $piece instanceof Pawn || $captured !== null ? 0 : $this->halfMove + 1;

        return new Position(
            $newPieces,
            $this->toMove === Colour::BLACK ? Colour::WHITE : Colour::BLACK,
            $this->castling->after($piece, $captured),
            $enPassantTarget,
            $halfMove,
            $this->toMove === Colour::BLACK ? $this->fullMove + 1 : $this->fullMove
        );
    }

    public function terminalState(): State
    {
        if ($this->isInsufficientMaterial()) {
            return State::INSUFFICIENT_MATERIAL;
        }

        if ($this->halfMove >= 100) {
            return State::FIFTY_MOVE_RULE;
        }

        $hasLegalMove = false;
        foreach ($this->pieces as $piece) {
            if ($piece->colour === $this->toMove && ! empty($this->legalMovesFor($piece))) {
                $hasLegalMove = true;
                break;
            }
        }

        if ($hasLegalMove) {
            return State::ACTIVE;
        }

        $king = $this->kingFor($this->toMove);
        $opponent = $this->toMove === Colour::WHITE ? Colour::BLACK : Colour::WHITE;

        return $this->isSquareAttacked($king->x, $king->y, $opponent)
            ? ($this->toMove === Colour::WHITE ? State::BLACK : State::WHITE)
            : State::STALEMATE;
    }

    private function isInsufficientMaterial(): bool
    {
        $whiteCount = 0;
        $blackCount = 0;
        $whiteHasMinor = false;
        $blackHasMinor = false;

        foreach ($this->pieces as $piece) {
            $isMinor = $piece instanceof Bishop || $piece instanceof Knight;

            if ($piece->colour === Colour::WHITE) {
                $whiteCount++;
                if ($isMinor) {
                    $whiteHasMinor = true;
                }
            } else {
                $blackCount++;
                if ($isMinor) {
                    $blackHasMinor = true;
                }
            }
        }

        if ($whiteCount > 2 || $blackCount > 2) {
            return false;
        }

        $whiteBare = $whiteCount === 1 || $whiteHasMinor;
        $blackBare = $blackCount === 1 || $blackHasMinor;

        return $whiteBare && $blackBare;
    }

    public function isThreefoldRepetition(Collection $fenHistory): bool
    {
        $positionKey = FENParser::positionKey(FENParser::encodeFenString($this));

        return $fenHistory->filter(fn ($fen) => FENParser::positionKey($fen) === $positionKey)->count() >= 3;
    }

    public function isSquareAttacked(int $x, int $y, Colour $byColour, array $overrides = []): bool
    {
        $slides = [
            [[[1, 0], [-1, 0], [0, 1], [0, -1]],   [Rook::class, Queen::class]],
            [[[1, 1], [1, -1], [-1, 1], [-1, -1]], [Bishop::class, Queen::class]],
        ];

        foreach ($slides as [$directions, $types]) {
            foreach ($directions as [$dx, $dy]) {
                $cx = $x + $dx;
                $cy = $y + $dy;
                while ($cx >= 0 && $cx <= 7 && $cy >= 0 && $cy <= 7) {
                    $piece = $this->pieceAtWithOverrides($cx, $cy, $overrides);
                    if ($piece) {
                        if ($piece->colour === $byColour && array_any($types, fn ($t) => $piece instanceof $t)) {
                            return true;
                        }
                        break;
                    }
                    $cx += $dx;
                    $cy += $dy;
                }
            }
        }

        foreach ([[1, 2], [2, 1], [-1, 2], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1]] as [$dx, $dy]) {
            $p = $this->pieceAtWithOverrides($x + $dx, $y + $dy, $overrides);
            if ($p instanceof Knight && $p->colour === $byColour) {
                return true;
            }
        }

        $pawnDy = $byColour === Colour::WHITE ? 1 : -1;
        foreach ([-1, 1] as $dx) {
            $p = $this->pieceAtWithOverrides($x + $dx, $y + $pawnDy, $overrides);
            if ($p instanceof Pawn && $p->colour === $byColour) {
                return true;
            }
        }

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1], [1, 1], [1, -1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            $p = $this->pieceAtWithOverrides($x + $dx, $y + $dy, $overrides);
            if ($p instanceof King && $p->colour === $byColour) {
                return true;
            }
        }

        return false;
    }

    public function pieceAt(int $x, int $y): ?Piece
    {
        return $this->index["$x,$y"] ?? null;
    }

    public function pieceAtWithOverrides(int $x, int $y, array $overrides): ?Piece
    {
        $key = "$x,$y";

        return array_key_exists($key, $overrides) ? $overrides[$key] : $this->pieceAt($x, $y);
    }

    public function legalMovesFor(Piece $piece): array
    {
        $king = $piece instanceof King ? null : $this->kingFor($piece->colour);
        $opponent = $piece->colour === Colour::WHITE ? Colour::BLACK : Colour::WHITE;

        return array_values(array_filter($piece->getSemiLegalMoves($this), function ($move) use ($piece, $king, $opponent) {
            $overrides = ["{$piece->x},{$piece->y}" => null, "{$move[0]},{$move[1]}" => $piece];

            [$kx, $ky] = $piece instanceof King ? $move : [$king->x, $king->y];

            return ! $this->isSquareAttacked($kx, $ky, $opponent, $overrides);
        }));
    }

    public function evaluatePosition(?State $state = null): int
    {
        $score = 0;
        $whiteFiles = [];
        $blackFiles = [];

        foreach ($this->pieces as $piece) {
            $value = match (true) {
                $piece instanceof Pawn => 100,
                $piece instanceof Knight => 350,
                $piece instanceof Bishop => 350,
                $piece instanceof Rook => 525,
                $piece instanceof Queen => 1000,
                $piece instanceof King => 10000,
            };

            $moveCount = $piece->countSemiLegalMoves($this);

            if ($piece->colour === Colour::WHITE) {
                $score += $value + $moveCount * 2;
                if ($piece instanceof Pawn) {
                    $whiteFiles[$piece->x] = ($whiteFiles[$piece->x] ?? 0) + 1;
                }
            } else {
                $score -= $value + $moveCount * 2;
                if ($piece instanceof Pawn) {
                    $blackFiles[$piece->x] = ($blackFiles[$piece->x] ?? 0) + 1;
                }
            }
        }

        $score -= count(array_filter($whiteFiles, fn ($count) => $count > 1));
        $score += count(array_filter($blackFiles, fn ($count) => $count > 1));

        return match ($state ?? $this->terminalState()) {
            State::WHITE => 1000000,
            State::BLACK => -1000000,
            State::ACTIVE => $score,
            default => 0,
        };
    }

    /**
     * Extends the search past the horizon with captures only, so alphaBeta
     * never stops mid-exchange and misjudges a losing trade as a gain.
     * Bounded naturally: every recursive call removes a piece from the board.
     */
    public function quiesce(int $alpha, int $beta, ?float $deadline = null): int
    {
        if ($deadline !== null && microtime(true) >= $deadline) {
            throw new SearchTimedOut;
        }

        $pieceValue = fn ($p) => match (true) {
            $p instanceof Pawn => 100, $p instanceof Knight, $p instanceof Bishop => 350,
            $p instanceof Rook => 525, $p instanceof Queen => 1000, $p instanceof King => 10000,
            default => 0,
        };

        $standPat = $this->evaluatePosition();
        $standPat = $this->toMove === Colour::WHITE ? $standPat : -$standPat;

        if ($standPat >= $beta) {
            return $beta;
        }

        if ($standPat > $alpha) {
            $alpha = $standPat;
        }

        $captures = [];
        foreach ($this->pieces as $piece) {
            if ($piece->colour !== $this->toMove) {
                continue;
            }
            foreach ($this->legalMovesFor($piece) as $destination) {
                $target = $this->pieceAt(...$destination);
                $isEnPassant = $piece instanceof Pawn && $this->enPassantTarget === $destination;

                if ($target !== null || $isEnPassant) {
                    $captures[] = [$piece, $destination, $target];
                }
            }
        }

        usort($captures, fn ($a, $b) => $pieceValue($b[2]) <=> $pieceValue($a[2]));

        foreach ($captures as [$piece, $destination]) {
            $candidate = new Move([$piece->x, $piece->y], $destination, 'queen');
            $score = -$this->applyMove($candidate)->quiesce(-$beta, -$alpha, $deadline);

            if ($score >= $beta) {
                return $beta;
            }

            if ($score > $alpha) {
                $alpha = $score;
            }
        }

        return $alpha;
    }

    /**
     * @param  array<int, int>  $repetitionCounts  Zobrist hash => how many times that exact
     *                                              position has already occurred, seeded from
     *                                              the real game history so the search can see
     *                                              draws by repetition that the game rules would
     *                                              actually enforce, not just ones it invents
     *                                              by shuffling pieces back and forth mid-search.
     */
    // https://www.youtube.com/watch?v=l-hh51ncgDI
    public function alphaBeta(int $alpha, int $beta, int $depthLeft, ZobristHasher $hasher, ?Move $rootMove = null, ?float $deadline = null, array $repetitionCounts = []): array
    {
        if ($deadline !== null && microtime(true) >= $deadline) {
            throw new SearchTimedOut;
        }

        $pieceValue = fn ($p) => match (true) {
            $p instanceof Pawn => 100, $p instanceof Knight, $p instanceof Bishop => 350,
            $p instanceof Rook => 525, $p instanceof Queen => 1000, $p instanceof King => 10000,
            default => 0,
        };

        if ($depthLeft === 0) {
            return [$this->quiesce($alpha, $beta, $deadline), $rootMove];
        }

        $ownHash = $hasher->hashPosition($this);
        $hintMove = ($hasher->hashedPosition[$ownHash] ?? null)['bestMove'] ?? null;

        $candidates = [];
        foreach ($this->pieces as $piece) {
            if ($piece->colour !== $this->toMove) {
                continue;
            }
            foreach ($this->legalMovesFor($piece) as $destination) {
                $candidates[] = [$piece, $destination];
            }
        }

        usort($candidates, function ($a, $b) use ($pieceValue, $hintMove) {
            // a remembered best move for this exact position (even from a search
            // too shallow to trust the score) is a stronger ordering signal than
            // capture value alone — trying it first triggers cutoffs sooner.
            $aIsHint = $hintMove !== null && $hintMove->from === [$a[0]->x, $a[0]->y] && $hintMove->to === $a[1];
            $bIsHint = $hintMove !== null && $hintMove->from === [$b[0]->x, $b[0]->y] && $hintMove->to === $b[1];

            if ($aIsHint !== $bIsHint) {
                return $aIsHint ? -1 : 1;
            }

            return $pieceValue($this->pieceAt(...$b[1])) <=> $pieceValue($this->pieceAt(...$a[1]));
        });

        $bestValue = [-1000000, $rootMove];
        $localBestMove = null;

        foreach ($candidates as [$piece, $destination]) {
            $candidate = new Move([$piece->x, $piece->y], $destination, 'queen');
            $moveToPropagate = $rootMove ?? $candidate;
            $newPosition = $this->applyMove($candidate);
            $positionKey = $hasher->hashPosition($newPosition);
            $occurrences = ($repetitionCounts[$positionKey] ?? 0) + 1;

            if ($occurrences >= 3) {
                // playing this move would make it the third occurrence of the same
                // position — the game rules let either side claim a draw here, so
                // treat it as one rather than trusting a positional eval that has
                // no idea a repetition just became available.
                $score = 0;
                $bestDeepMove = $moveToPropagate;
            } else {
                $hashed = $hasher->hashedPosition[$positionKey] ?? null;
                if ($hashed !== null && $hashed['depth'] >= $depthLeft - 1 && $hashed['bound'] !== 'lower') {
                    $score = $hashed['score'];
                    $bestDeepMove = $moveToPropagate;
                } else {
                    [$score, $bestDeepMove] = $newPosition->alphaBeta(-$beta, -$alpha, $depthLeft - 1, $hasher, $moveToPropagate, $deadline, [...$repetitionCounts, $positionKey => $occurrences]);
                }
            }

            $score = -$score;
            if ($score > $bestValue[0]) {
                $bestValue = [$score, $bestDeepMove];
                $localBestMove = $candidate;
                if ($score > $alpha) {
                    $alpha = $score;
                }
            }

            if ($score >= $beta) {
                $hasher->hashedPosition[$ownHash] = [
                    'score' => $bestValue[0],
                    'depth' => $depthLeft,
                    'bestMove' => $localBestMove,
                    'bound' => 'lower',
                ];

                return $bestValue;
            }
        }

        $hasher->hashedPosition[$ownHash] = [
            'score' => $bestValue[0],
            'depth' => $depthLeft,
            'bestMove' => $localBestMove,
            'bound' => 'exact',
        ];

        return $bestValue;
    }

    /**
     * Search progressively deeper (1, 2, 3, ... $maxDepth), reusing the same
     * transposition table across iterations so each pass orders moves using
     * the best move found by the previous, shallower pass — this triggers
     * alpha-beta cutoffs sooner than searching $maxDepth cold. If $timeLimit
     * (seconds) is given, alphaBeta/quiesce abort mid-search once it elapses
     * (checked at every node, not just between depths) and this returns the
     * best move found by the last fully completed depth. Depth 1 is never
     * cut off, so a legal move is always available even under a tight budget.
     *
     * @param  array<int, int>  $repetitionCounts  Zobrist hash => occurrences so far in the
     *                                              real game, so the search can see repetition
     *                                              draws coming. See alphaBeta() for details.
     * @return array{0: int, 1: ?Move}
     */
    public function iterativeDeepening(int $maxDepth, ZobristHasher $hasher, ?float $timeLimit = null, array $repetitionCounts = []): array
    {
        $deadline = $timeLimit !== null ? microtime(true) + $timeLimit : null;
        $best = [0, null];

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            try {
                $result = $this->alphaBeta(-1000000, 1000000, $depth, $hasher, null, $depth === 1 ? null : $deadline, $repetitionCounts);
            } catch (SearchTimedOut) {
                break;
            }

            if ($result[1] !== null) {
                $best = $result;
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
        }

        return $best;
    }

    private function kingFor(Colour $colour): Piece
    {
        $cache = $colour === Colour::WHITE ? 'whiteKing' : 'blackKing';
        if ($this->$cache !== null) {
            return $this->$cache;
        }

        foreach ($this->pieces as $piece) {
            if ($piece instanceof King && $piece->colour === $colour) {
                return $this->$cache = $piece;
            }
        }

        throw new \RuntimeException("No {$colour->value} king found on the board.");
    }
}
