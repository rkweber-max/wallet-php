<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:api')->group(function () {
    Route::apiResource('wallets', WalletController::class)->only(['store', 'index', 'show']);
    Route::get('/wallets/{wallet}/transactions', [WalletController::class, 'getTransactions']);
    Route::post('/wallets/{wallet}/deposit', [WalletController::class, 'deposit']);
    Route::post('/wallets/{wallet}/withdraw', [WalletController::class, 'withdraw']);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');