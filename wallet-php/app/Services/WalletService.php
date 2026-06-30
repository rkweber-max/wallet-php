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

    public function getWallet(Wallet $wallet)
    {
        return $wallet;
    }

    public function getTransactions(Wallet $wallet)
    {
        return $wallet->transactions()->get();
    }

    public function deposit(Wallet $wallet, int $amount): Wallet
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($wallet, $amount) {
            $lockWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $lockWallet->balance += $amount;
            $lockWallet->save();

            $lockWallet->transactions()->create([
                'type' => TransactionType::DEPOSIT->value,
                'amount' => $amount,
                'balance_after' => $lockWallet->balance,
            ]);

            return $lockWallet;
        });
    }

    public function withdraw(Wallet $wallet, int $amount): Wallet
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($wallet, $amount) {
            $lockWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($lockWallet->balance < $amount) {
                throw new InsufficientBalanceException('Insufficient balance for withdrawal.');
            }

            $lockWallet->balance -= $amount;
            $lockWallet->save();

            $lockWallet->transactions()->create([
                'type' => TransactionType::WITHDRAWAL->value,
                'amount' => $amount,
                'balance_after' => $lockWallet->balance,
            ]);

            return $lockWallet;
        });
    }

    private function validateAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
    }
}
