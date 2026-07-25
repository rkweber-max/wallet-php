<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

class CreateWalletCommand extends Command 
{
    protected $signature = 'wallet:create {user_id}';
    protected $description = 'Cria uma nova wallet para um usuário';

    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): void
    {
        $userId = $this->argument('user_id');
        $wallet = $walletService->createWallet($userId);
        
        $this->info("Wallet created successfully for user: {$userId}");
        $this->line("ID: {$wallet->id}");
        $this->line("Saldo: {$wallet->balance}");
    }
}
