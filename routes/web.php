<?php

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'OK', 'database' => 'up'], 200);
    } catch (Exception $e) {
        return response()->json(['status' => 'Error', 'database' => 'down'], 503);
    }
});

Route::get('/metrics', function () {
    $deposits = Transaction::where('type', 'deposit')->count();
    $withdrawals = Transaction::where('type', 'withdrawal')->count();
    $wallets = Wallet::count();

    $output = '';
    $output .= "# HELP deposits_total Total number of deposits\n";
    $output .= "# TYPE deposits_total counter\n";
    $output .= "deposits_total {$deposits}\n";
    $output .= "# HELP withdrawals_total Total number of withdrawals\n";
    $output .= "# TYPE withdrawals_total counter\n";
    $output .= "withdrawals_total {$withdrawals}\n";
    $output .= "# HELP wallets_total Total number of wallets\n";
    $output .= "# TYPE wallets_total gauge\n";
    $output .= "wallets_total {$wallets}\n";

    return response($output, 200)->header('Content-Type', 'text/plain');
});