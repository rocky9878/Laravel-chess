<?php

namespace App\Http\Controllers;

use App\Enums\Colour;
use App\Http\Requests\MakeMoveRequest;
use App\Models\Board;
use App\Models\Objects\Move;
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

        return inertia('Board', [
            'board' => $board->id,
            'player_colour' => $board->white === auth()->user()?->id ? Colour::WHITE : Colour::BLACK,
            'pieces' => $board->piecesForFrontend(),
            'toMove' => $board->position->toMove,
            'state' => $board->state,
            'score' => $board->position->evaluatePosition($board->state),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Board $board)
    {
        if ($request->user()->cannot('view', $board)) {
            abort(403);
        }

        return inertia('Board', [
            'board' => $board->id,
            'player_colour' => $board->white === auth()->user()?->id ? Colour::WHITE : Colour::BLACK,
            'pieces' => $board->piecesForFrontend(),
            'toMove' => $board->position->toMove,
            'state' => $board->state,
            'score' => $board->position->evaluatePosition($board->state),
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
        if ($request->user()->cannot('update', $board)) {
            abort(403);
        }

        $board->makeMove(new Move($request->from, $request->to, $request->validated('promotion')));

        return redirect()->route('board.show', $board);
    }

    /**
     * Make the computer's move for the given board.
     */
    public function computerMove(Board $board)
    {
        $board->makeBestMove(20, 5);

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
