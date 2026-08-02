<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function createUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Test User',
        'email' => 'user-' . uniqid() . '@example.com',
        'password' => 'password123',
    ], $overrides));
}

/**
 * Retorna os headers de autenticação para um usuário (gera um token JWT).
 */
function authHeaders(User $user): array
{
    $token = auth('api')->login($user);

    return [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ];
}

/*
|--------------------------------------------------------------------------
| Criação de carteira
|--------------------------------------------------------------------------
*/

test('creates a wallet for the authenticated user', function () {
    $user = createUser();

    $response = $this->withHeaders(authHeaders($user))
        ->postJson('/api/wallets');

    $response->assertStatus(201)
        ->assertJsonPath('data.balance_in_cents', 0);

    // a carteira criada pertence ao usuário autenticado
    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 0,
    ]);
});

test('cannot create a wallet without a token', function () {
    $response = $this->postJson('/api/wallets');

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Listagem e isolamento entre usuários
|--------------------------------------------------------------------------
| O teste de segurança mais importante: um usuário não pode ver as
| carteiras de outro.
*/

test('user only sees their own wallets', function () {
    $alice = createUser();
    $bob = createUser();

    // Alice cria uma carteira
    Wallet::create(['user_id' => $alice->id, 'balance' => 0]);

    // Bob lista as carteiras — não deve ver a de Alice
    $response = $this->withHeaders(authHeaders($bob))
        ->getJson('/api/wallets');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('listing returns the wallets of the authenticated user', function () {
    $user = createUser();
    Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    Wallet::create(['user_id' => $user->id, 'balance' => 500]);

    $response = $this->withHeaders(authHeaders($user))
        ->getJson('/api/wallets');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

/*
|--------------------------------------------------------------------------
| Depósito
|--------------------------------------------------------------------------
*/

test('deposit increases the balance', function () {
    $user = createUser();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

    $response = $this->withHeaders(authHeaders($user))
        ->postJson("/api/wallets/{$wallet->id}/deposit", ['amount' => 5000]);

    $response->assertStatus(200)
        ->assertJsonPath('data.balance_in_cents', 5000);

    $this->assertDatabaseHas('transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'deposit',
        'amount' => 5000,
        'balance_after' => 5000,
    ]);
});

test('deposit rejects a non positive amount', function () {
    $user = createUser();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

    $response = $this->withHeaders(authHeaders($user))
        ->postJson("/api/wallets/{$wallet->id}/deposit", ['amount' => 0]);

    // Form Request barra amount < 1
    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Saque
|--------------------------------------------------------------------------
*/

test('withdrawal decreases the balance', function () {
    $user = createUser();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 10000]);

    $response = $this->withHeaders(authHeaders($user))
        ->postJson("/api/wallets/{$wallet->id}/withdraw", ['amount' => 3000]);

    $response->assertStatus(200)
        ->assertJsonPath('data.balance_in_cents', 7000);
});

test('withdrawal with insufficient balance returns 422', function () {
    $user = createUser();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 2000]);

    $response = $this->withHeaders(authHeaders($user))
        ->postJson("/api/wallets/{$wallet->id}/withdraw", ['amount' => 5000]);

    // o handler global converte InsufficientBalanceException em 422
    $response->assertStatus(422);

    // o saldo permanece intacto (rollback)
    $this->assertDatabaseHas('wallets', [
        'id' => $wallet->id,
        'balance' => 2000,
    ]);
});

/*
|--------------------------------------------------------------------------
| Histórico
|--------------------------------------------------------------------------
*/

test('transactions history lists the wallet movements', function () {
    $user = createUser();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    $headers = authHeaders($user);

    $this->withHeaders($headers)
        ->postJson("/api/wallets/{$wallet->id}/deposit", ['amount' => 5000]);
    $this->withHeaders($headers)
        ->postJson("/api/wallets/{$wallet->id}/withdraw", ['amount' => 2000]);

    $response = $this->withHeaders($headers)
        ->getJson("/api/wallets/{$wallet->id}/transactions");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});
