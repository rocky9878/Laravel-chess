<?php

namespace App\Http\Controllers;

use App\Enums\Colour;
use App\Http\Requests\MakeMoveRequest;
use App\Models\Board;

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
            'pieces' => $board->pieces,
            'state'  => $board->state,
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
            'pieces' => $board->pieces,
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

        $board->movePiece($request->to[0], $request->to[1], $piece, $request->validated('promotion'));

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
