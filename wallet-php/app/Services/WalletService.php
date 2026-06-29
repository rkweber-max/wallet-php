<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\TransactionType;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function createWallet(string $userId): Wallet
    {
        $wallet = Wallet::create([
            'user_id' => $userId,
            'balance' => 0,
        ]);

        return $wallet;
    }

    public function getWallets()
    {
        return Wallet::all();
    }

    public function deposit(Wallet $wallet, int $amount): Wallet
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($wallet, $amount) {
            $wallet->balance += $amount;
            $wallet->save();

            $wallet->transactions()->create([
                'type' => TransactionType::DEPOSIT->value,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
            ]);

            return $wallet;
        });
    }

    public function withdraw(Wallet $wallet, int $amount): Wallet
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($wallet, $amount) {
            if ($wallet->balance < $amount) {
                throw new InsufficientBalanceException('Insufficient balance for withdrawal.');
            }

            $wallet->balance -= $amount;
            $wallet->save();

            $wallet->transactions()->create([
                'type' => TransactionType::WITHDRAWAL->value,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
            ]);

            return $wallet;
        });
    }

    private function validateAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
    }
}
