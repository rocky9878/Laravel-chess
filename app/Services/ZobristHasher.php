<?php

namespace App\Services;

use App\Enums\Colour;
use App\Models\Objects\Position;

final class ZobristHasher
{
    private $pieceHashValues;

    private $stateHashValues;

    public array $hashedPosition = []; // "ZobristHash" => [saved info]

    public function __construct()
    {
        for ($i = 0; $i < 8; $i++) {
            for ($x = 0; $x < 8; $x++) {
                $this->pieceHashValues[$i][$x] = [
                    'r' => rand(1000, 9999999),
                    'n' => rand(1000, 9999999),
                    'b' => rand(1000, 9999999),
                    'q' => rand(1000, 9999999),
                    'k' => rand(1000, 9999999),
                    'p' => rand(1000, 9999999),
                    'R' => rand(1000, 9999999),
                    'N' => rand(1000, 9999999),
                    'B' => rand(1000, 9999999),
                    'Q' => rand(1000, 9999999),
                    'K' => rand(1000, 9999999),
                    'P' => rand(1000, 9999999),
                ];
            }
        }

        $this->stateHashValues['blackToMove'] = rand(1000, 9999999);
        $this->stateHashValues['castleRights'] = [
            'whiteKingSide' => rand(1000, 9999999),
            'whiteQueenSide' => rand(1000, 9999999),
            'blackKingSide' => rand(1000, 9999999),
            'blackQueenSide' => rand(1000, 9999999),
        ];
        $this->stateHashValues['enPassantFile'] = [
            0 => rand(1000, 9999999),
            1 => rand(1000, 9999999),
            2 => rand(1000, 9999999),
            3 => rand(1000, 9999999),
            4 => rand(1000, 9999999),
            5 => rand(1000, 9999999),
            6 => rand(1000, 9999999),
            7 => rand(1000, 9999999),
        ];
    }

    public function hashPosition(Position $position)
    {
        $hash = 0;

        foreach ($position->pieces as $piece) {
            $hash ^= $this->pieceHashValues[$piece->x][$piece->y][$piece->getClassChar()];
        }

        if ($position->toMove === Colour::BLACK) {
            $hash ^= $this->stateHashValues['blackToMove'];
        }

        if ($position->castling->whiteKingSide) {
            $hash ^= $this->stateHashValues['castleRights']['whiteKingSide'];
        }
        if ($position->castling->whiteQueenSide) {
            $hash ^= $this->stateHashValues['castleRights']['whiteQueenSide'];
        }
        if ($position->castling->blackKingSide) {
            $hash ^= $this->stateHashValues['castleRights']['blackKingSide'];
        }
        if ($position->castling->blackQueenSide) {
            $hash ^= $this->stateHashValues['castleRights']['blackQueenSide'];
        }

        if ($position->enPassantTarget) {
            $hash ^= $this->stateHashValues['enPassantFile'][$position->enPassantTarget[0]];
        }

        return $hash;
    }
}
