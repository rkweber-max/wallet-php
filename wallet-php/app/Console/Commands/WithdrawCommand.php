<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class WithdrawCommand extends Command
{
    protected $signature = 'wallet:withdraw {wallet_id} {amount}';
    protected $description = 'Realiza um saque em uma wallet';
    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): void
    {
         try {
            $walletId = $this->argument('wallet_id');
            $amount = (int) $this->argument('amount');

            $wallet = Wallet::findOrFail($walletId);
            $wallet = $walletService->withdraw($wallet, $amount);

            $this->info("Saque de {$amount} realizado com sucesso na wallet: {$walletId}");
            $this->line("Novo saldo: {$wallet->balance}");
        } catch (\Exception $e) {
            $this->error("Erro ao realizar o saque: " . $e->getMessage());
        }
    }
}
