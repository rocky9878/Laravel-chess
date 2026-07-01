<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Colour;
use App\Models\BoardState;
use App\Models\Pieces\Bishop;
use App\Models\Pieces\King;
use App\Models\Pieces\Knight;
use App\Models\Pieces\Pawn;
use App\Models\Pieces\Queen;
use App\Models\Pieces\Rook;
use Illuminate\Support\Collection;


final class FENParser
{
    static public function decodeFenString(string $fen)
    {
        // dd($fen);
        $pieceInfo = substr($fen, 0, strpos($fen, " ")); // all characters before the board characters

        $stateInfo = substr($fen, strpos($fen, " ") + 1); // all characters after the piece characters

        $pieces = collect(explode('/', $pieceInfo))->map(function(string $row, int $y) use ($stateInfo) {
            $rowPieces = [];
            $x = 0;
            $enPassant = explode(' ', $stateInfo)[2];

            foreach(str_split($row) as $char) {
                if (is_numeric($char)) {
                    $x += intval($char);
                } else {
                    $rowPieces[] = match($char) {
                        'r' => new Rook($x, $y, Colour::BLACK, (str_contains($stateInfo, 'k') && $x === 0) || (str_contains($stateInfo, 'q') && $x === 7) ? false : true),
                        'n' => new Knight($x, $y, Colour::BLACK),
                        'b' => new Bishop($x, $y, Colour::BLACK),
                        'q' => new Queen($x, $y, Colour::BLACK),
                        'k' => new King($x, $y, Colour::BLACK, str_contains($stateInfo, 'k') || str_contains($stateInfo, 'q') ? false : true),
                        'p' => new Pawn($x, $y, Colour::BLACK, $y === 1 ? false : true, ($x == strpos('abcdefgh', mb_substr($enPassant, 0 , 1)) && 6 == mb_substr($enPassant, 1, 1))),
                        'R' => new Rook($x, $y, Colour::WHITE, (str_contains($stateInfo, 'K') && $x === 0) || (str_contains($stateInfo, 'Q') && $x === 7) ? false : true),
                        'N' => new Knight($x, $y, Colour::WHITE),
                        'B' => new Bishop($x, $y, Colour::WHITE),
                        'Q' => new Queen($x, $y, Colour::WHITE),
                        'K' => new King($x, $y, Colour::WHITE, str_contains($stateInfo, 'K') || str_contains($stateInfo, 'Q') ? false : true),
                        'P' => new Pawn($x, $y, Colour::WHITE, $y === 6 ? false : true, ($x == strpos('abcdefgh', mb_substr($enPassant, 0 , 1)) && 3 == mb_substr($enPassant, 1, 1))),
                        default => null,
                    };
                    $x++;
                }
            }

            return $rowPieces;
        })->flatten()->filter()->values();

        $decoded['pieces'] = $pieces;
        $decoded['state'] = new BoardState(
            $stateInfo[0] === 'w' ? Colour::WHITE : Colour::BLACK,
            explode(' ', $stateInfo)[3],
            explode(' ', $stateInfo)[4],
        );

        return $decoded;
    }

    static public function encodeFenString(Collection $pieces, BoardState $state): string
    {
        $string = '';

        for($y = 0; $y < 8; $y++) {
            $increment = 0;
            for($x = 0; $x < 8; $x++) {
                $piece = $pieces->where('x', $x)->where('y', $y)->first();
                if($piece) {
                    $string .= match (true) {
                        $piece instanceof Pawn && $piece->colour === Colour::BLACK => 'p',
                        $piece instanceof Knight && $piece->colour === Colour::BLACK => 'n',
                        $piece instanceof Bishop && $piece->colour === Colour::BLACK => 'b',
                        $piece instanceof Rook && $piece->colour === Colour::BLACK => 'r',
                        $piece instanceof Queen && $piece->colour === Colour::BLACK => 'q',
                        $piece instanceof King && $piece->colour === Colour::BLACK => 'k',
                        $piece instanceof Pawn && $piece->colour === Colour::WHITE => 'P',
                        $piece instanceof Knight && $piece->colour === Colour::WHITE => 'N',
                        $piece instanceof Bishop && $piece->colour === Colour::WHITE => 'B',
                        $piece instanceof Rook && $piece->colour === Colour::WHITE => 'R',
                        $piece instanceof Queen && $piece->colour === Colour::WHITE => 'Q',
                        $piece instanceof King && $piece->colour === Colour::WHITE => 'K',
                    };
                } else {
                    $increment++;
                }
                if (($pieces->where('x', $x + 1)->where('y', $y)->first() || $x == 7) && !$piece) {
                    $string .= $increment;
                    $increment = 0;
                }
            }
            $string .= ($y !== 7 ? '/' : ' ');
        }

        $string .= ($state->toMove === Colour::WHITE ? 'w ' : 'b ');

        $castling = '';

        if(($kings = $pieces->filter(fn($piece) => $piece instanceOf King && $piece->hasMoved === false))->isNotEmpty()) {
            $rooks = $pieces->filter(fn($piece) => $piece instanceOf Rook && $piece->hasMoved === false);
            if($kings->where('colour', Colour::WHITE)->first() && $rooks->isNotEmpty()) {
                if($rooks->where('colour', Colour::WHITE)->where('x', 7)) $castling .= 'K';
                if($rooks->where('colour', Colour::WHITE)->where('x', 0)) $castling .= 'Q';
            }
            if($kings->where('colour', Colour::BLACK)->first() && $rooks->isNotEmpty()) {
                if($rooks->where('colour', Colour::BLACK)->where('x', 7)) $castling .= 'k';
                if($rooks->where('colour', Colour::BLACK)->where('x', 0)) $castling .= 'q';
            }
        }

        $string .= $castling ?: '-';

        $passantTarget = $pieces->where('canBeCapturedEnPassant', true)->first();
        $string .= $passantTarget ? ' '.chr(ord('a') + $passantTarget->x) . ($passantTarget->colour === Colour::WHITE ? 3 : 6) : ' -';

        $string .= ' ' . $state->halfMove;
        $string .= ' ' . $state->fullMove;
        \Log::info($string);
        return $string;
    }
}
