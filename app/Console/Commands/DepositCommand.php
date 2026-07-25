<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Console\Command;


class DepositCommand extends Command
{
    protected $signature = 'wallet:deposit {wallet_id} {amount}';
    protected $description = 'Deposita um valor em uma wallet';

    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): void
    {
        try {
            $walletId = $this->argument('wallet_id');
            $amount = (int) $this->argument('amount');

            $wallet = Wallet::findOrFail($walletId);
            $wallet = $walletService->deposit($wallet, $amount);

            $this->info("Depósito de {$amount} realizado com sucesso na wallet: {$walletId}");
            $this->line("Novo saldo: {$wallet->balance}");
        } catch (\Exception $e) {
            $this->error("Erro ao realizar o depósito: " . $e->getMessage());
        }
    }
}
