<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Http\Requests\WalletRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\WalletService;

class WalletController extends Controller
{
    public function __construct (
        protected WalletService $walletService,
    ) {}

    public function store (WalletRequest $request)
    {
        $wallet = $this->walletService->createWallet($request->input('user_id'));

        return (new WalletResource($wallet))->response()->setStatusCode(201);
    }

    public function index ()
    {
        $wallets = $this->walletService->getWallets();
        return WalletResource::collection($wallets);
    }

    public function show (Wallet $wallet)
    {
        $wallet = $this->walletService->getWallet($wallet);
        return (new WalletResource($wallet))->response()->setStatusCode(200);
    }

    public function getTransactions (Wallet $wallet)
    {
        $transactions = $this->walletService->getTransactions($wallet);
        return TransactionResource::collection($transactions);
    }

    public function deposit (Wallet $wallet, TransactionRequest $request)
    {
        $wallet = $this->walletService->deposit($wallet, $request->input('amount'));

        return (new WalletResource($wallet))->response()->setStatusCode(200);
    }

    public function withdraw (Wallet $wallet, TransactionRequest $request)
    {
        $wallet = $this->walletService->withdraw($wallet, $request->input('amount'));

        return (new WalletResource($wallet))->response()->setStatusCode(200);
    }
}
