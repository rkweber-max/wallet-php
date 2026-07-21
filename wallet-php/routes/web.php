<?php

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
