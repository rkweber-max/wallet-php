<?php

use App\Exceptions\InsufficientBalanceException;
use App\Services\WalletService;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;

uses(DatabaseTruncation::class);

test('increases balance on deposit', function () {
    $user = User::create(['name' => 'Test User']);

    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
    ]);

    $walletService = new WalletService();
    $walletService->deposit($wallet, 5000);

    expect($wallet->fresh()->balance)->toBe(5000);
});

test('decreases balance on withdrawal', function () {
    $user = User::create(['name' => 'Test User']);

    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 10000]);

    $walletService = new WalletService();
    $walletService->withdraw($wallet, 3000);

    expect($wallet->fresh()->balance)->toBe(7000);
});

test('race condition: two simultaneous withdrawals, only one succeeds', function () {
    $user = User::create(['name' => 'Test User']);
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 10000]);
    $walletId = $wallet->id;

    $command = base_path('artisan');

    $cmd = sprintf(
        'DB_DATABASE=wallet_test php %s wallet:withdraw %s 10000 & ' .
            'DB_DATABASE=wallet_test php %s wallet:withdraw %s 10000 & ' .
            'wait',
        $command,
        $walletId,
        $command,
        $walletId
    );

    exec($cmd, $output, $returnCode);

    expect($wallet->fresh()->balance)->toBe(0);
    expect($wallet->fresh()->transactions()->count())->toBe(1);
});

test('throws exception when withdrawing more than available balance', function () {
    $user = User::create(['name' => 'Test User']);

    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 2000]);

    $walletService = new WalletService();

    expect(fn() => $walletService->withdraw($wallet, 3000))->toThrow(InsufficientBalanceException::class);
});

test('does not change balance or create transaction when withdrawal fails', function () {
    $user = User::create(['name' => 'Test User']);
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 2000]);
    $walletService = new WalletService();

    try {
        $walletService->withdraw($wallet, 3000);
    } catch (InsufficientBalanceException $e) {
    }

    expect($wallet->fresh()->balance)->toBe(2000);
    expect($wallet->fresh()->transactions()->count())->toBe(0);
});

test('records the transaction on deposit', function () {
    $user = User::create(['name' => 'Test User']);
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    $walletService = new WalletService();

    $walletService->deposit($wallet, 5000);

    $this->assertDatabaseHas('transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'deposit',
        'amount' => 5000,
        'balance_after' => 5000,
    ]);
});