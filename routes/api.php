<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/users', [UserController::class, 'store']);
Route::get('/users', [UserController::class, 'index'])->middleware('auth');
Route::patch('/users/{user}', [UserController::class, 'update'])->middleware(['auth', 'can.edit.user:user']);
