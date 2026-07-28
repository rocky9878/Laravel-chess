<?php

use App\Http\Controllers\BoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [BoardController::class, 'index'])->name('home');
    Route::get('/board/{board}', [BoardController::class, 'show'])->name('board.show');
    Route::post('/board/{board}', [BoardController::class, 'update'])->name('board.update');
    Route::post('/board/{board}/computer-move', [BoardController::class, 'computerMove'])->name('board.computerMove');
});

require __DIR__.'/settings.php';
