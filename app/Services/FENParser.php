<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Colour;
use App\Models\Board;
use App\Models\Objects\CastleRights;
use App\Models\Objects\Position;
use App\Models\Pieces\Bishop;
use App\Models\Pieces\King;
use App\Models\Pieces\Knight;
use App\Models\Pieces\Pawn;
use App\Models\Pieces\Queen;
use App\Models\Pieces\Rook;

final class FENParser
{
    public static function decodeFenString(string $fen)
    {
        $pieceInfo = substr($fen, 0, strpos($fen, ' ')); // all characters before the board characters

        $stateInfo = substr($fen, strpos($fen, ' ') + 1); // all characters after the piece characters

        $pieces = [];

        foreach (explode('/', $pieceInfo) as $y => $row) {
            $x = 0;

            foreach (str_split($row) as $char) {
                if (is_numeric($char)) {
                    $x += intval($char);
                } else {
                    $piece = match ($char) {
                        'r' => new Rook($x, $y, Colour::BLACK),
                        'n' => new Knight($x, $y, Colour::BLACK),
                        'b' => new Bishop($x, $y, Colour::BLACK),
                        'q' => new Queen($x, $y, Colour::BLACK),
                        'k' => new King($x, $y, Colour::BLACK),
                        'p' => new Pawn($x, $y, Colour::BLACK, $y === 1 ? false : true),
                        'R' => new Rook($x, $y, Colour::WHITE),
                        'N' => new Knight($x, $y, Colour::WHITE),
                        'B' => new Bishop($x, $y, Colour::WHITE),
                        'Q' => new Queen($x, $y, Colour::WHITE),
                        'K' => new King($x, $y, Colour::WHITE),
                        'P' => new Pawn($x, $y, Colour::WHITE, $y === 6 ? false : true),
                        default => null,
                    };

                    if ($piece !== null) {
                        $pieces[] = $piece;
                    }

                    $x++;
                }
            }
        }

        $castleRights = new CastleRights(str_contains($stateInfo, 'K'), str_contains($stateInfo, 'Q'), str_contains($stateInfo, 'k'), str_contains($stateInfo, 'q'));

        $enPassantStr = explode(' ', $stateInfo)[2];
        $enPassantTarget = [];

        if (strlen($enPassantStr) === 2) {
            $file = $enPassantStr[0];           // 'a'..'h'
            $rank = (int) substr($enPassantStr, 1); // '1'..'8'

            $enPassantTarget = [ord($file) - ord('a'), 8 - $rank];
        }

        return new Position($pieces, $stateInfo[0] === 'w' ? Colour::WHITE : Colour::BLACK, $castleRights, $enPassantTarget, intval(explode(' ', $stateInfo)[3]), intval(explode(' ', $stateInfo)[4]));
    }

    public static function encodeFenString(Position $position): string
    {
        $string = '';

        for ($y = 0; $y < 8; $y++) {
            $increment = 0;
            for ($x = 0; $x < 8; $x++) {
                $piece = $position->pieceAt($x, $y);
                if ($piece) {
                    $string .= $piece->getClassChar();
                } else {
                    $increment++;
                }
                if (($position->pieceAt($x + 1, $y) || $x == 7) && ! $piece) {
                    $string .= $increment;
                    $increment = 0;
                }
            }
            $string .= ($y !== 7 ? '/' : ' ');
        }

        $string .= ($position->toMove === Colour::WHITE ? 'w ' : 'b ');

        $castling = ($position->castling->whiteKingSide ? 'K' : '')
            .($position->castling->whiteQueenSide ? 'Q' : '')
            .($position->castling->blackKingSide ? 'k' : '')
            .($position->castling->blackQueenSide ? 'q' : '');

        $string .= $castling ?: '-';

        $string .= ' '.($position->enPassantTarget ? Board::toAlgebraic(...$position->enPassantTarget) : '-');

        $string .= ' '.$position->halfMove;
        $string .= ' '.$position->fullMove;

        return $string;
    }

    public static function positionKey(string $fen): string
    {
        return implode(' ', array_slice(explode(' ', $fen), 0, 4));
    }
}
