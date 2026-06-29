<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletRequest;
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
        return $this->walletService->getWallets();
    }
}
