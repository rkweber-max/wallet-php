<?php

use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('wallets', WalletController::class)->only(['store', 'index', 'show']);
Route::get('/wallets/{wallet}/transactions', [WalletController::class, 'getTransactions']);
Route::post('/wallets/{wallet}/deposit', [WalletController::class, 'deposit']);
Route::post('/wallets/{wallet}/withdraw', [WalletController::class, 'withdraw']);