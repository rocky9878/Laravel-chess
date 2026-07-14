<?php

namespace App\Http\Controllers;

use App\Http\Requests\MakeMoveRequest;
use App\Models\Board;
use App\Models\Pieces\Pawn;
use App\Services\FENParser;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        $board = Board::where('white', auth()->id())
            ->withCount('states')
            ->having('states_count', '=', 0)
            ->first();

        if (! $board) {
            $board = Board::create(['white' => auth()->id()]);
        }

        $pieces = $board->pieces->each(fn($piece) => $piece->legalMoves = $piece->getSemiLegalMoves($board->pieces));

        $pieces->each(fn($piece) =>
            $piece->legalMoves = $piece->legalMoves?->filter(fn($move) => $board->testIfMoveIsLegal($piece, $move))->values()
        );

        return inertia('Board', [
            'board'  => $board->id,
            'pieces' => $pieces,
            'state'  => $board->state,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Board $board)
    {
        $pieces = $board->pieces->each(fn($piece) => $piece->legalMoves = $piece->getSemiLegalMoves($board->pieces));

        $pieces->each(fn($piece) =>
            $piece->legalMoves = $piece->legalMoves?->filter(fn($move) => $board->testIfMoveIsLegal($piece, $move))->values()
        );

        return inertia('Board', [
            'board'  => $board->id,
            'pieces' => $pieces,
            'state'  => $board->state,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Board $board)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MakeMoveRequest $request, Board $board)
    {
        $piece = $board->pieces->where('x', $request->from[0])->where('y', $request->from[1])->first();

        $board->movePiece($request->to[0], $request->to[1], $piece, $request->promotion);

        return redirect()->route('board.show', $board);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Board $board)
    {
        //
    }
}
