<?php

use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Game search and lookup
Route::get('/games', [GameController::class, 'search']);
Route::get('/games/{id}', [GameController::class, 'show']);
