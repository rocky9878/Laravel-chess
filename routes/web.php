<?php

use App\Http\Controllers\BoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('/', [BoardController::class, 'index'])->name('home');
    Route::get('/board/{board}', [BoardController::class, 'show'])->name('board.show');
    Route::post('/board/{board}', [BoardController::class, 'update'])->name('board.update');
});

require __DIR__.'/settings.php';
