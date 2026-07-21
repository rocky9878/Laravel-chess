<?php

namespace App\Http\Controllers;

use App\Enums\Colour;
use App\Http\Requests\MakeMoveRequest;
use App\Models\Board;
use App\Models\Objects\Move;

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

        return inertia('Board', [
            'board'  => $board->id,
            'player_colour' => $board->white === auth()->user()?->id ? Colour::WHITE : Colour::BLACK,
            'pieces' => $board->piecesForFrontend(),
            'toMove'  => $board->position->toMove,
            'state'  => $board->state,
            'score' => $board->position->evaluatePosition()
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Board $board)
    {
        return inertia('Board', [
            'board'  => $board->id,
            'player_colour' => $board->white === auth()->user()?->id ? Colour::WHITE : Colour::BLACK,
            'pieces' => $board->piecesForFrontend(),
            'toMove'  => $board->position->toMove,
            'state'  => $board->state,
            'score' => $board->position->evaluatePosition()
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
        $board->makeMove($request->from, $request->to, $request->validated('promotion'));

        $board->makeBestMove();

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
