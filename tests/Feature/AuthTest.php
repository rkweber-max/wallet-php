<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
| Cria um usuário de teste. A senha é passada em texto puro e o cast
| 'hashed' do model cuida de transformá-la em hash antes de salvar.
*/
function makeUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Test User',
        'email' => 'user-' . uniqid() . '@example.com',
        'password' => 'password123',
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Registro
|--------------------------------------------------------------------------
*/

test('register creates a user and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Rodrigo',
        'email' => 'rodrigo@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user', 'token']);

    // o usuário foi realmente persistido
    $this->assertDatabaseHas('users', ['email' => 'rodrigo@example.com']);
});

test('register does not expose the password in the response', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Rodrigo',
        'email' => 'rodrigo@example.com',
        'password' => 'password123',
    ]);

    // o $hidden do model deve omitir a senha
    $response->assertJsonMissing(['password' => 'password123']);
    expect($response->json('user'))->not->toHaveKey('password');
});

test('register rejects a duplicate email', function () {
    makeUser(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Another',
        'email' => 'taken@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
});

test('register rejects invalid data', function () {
    $response = $this->postJson('/api/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => '123', // curta demais
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

test('login with valid credentials returns a token', function () {
    makeUser([
        'email' => 'valid@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'valid@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token']);
});

test('login with wrong password returns 401', function () {
    makeUser([
        'email' => 'valid@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'valid@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

test('login with unknown email returns 401', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'ghost@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Rotas protegidas
|--------------------------------------------------------------------------
*/

test('protected route without a token returns 401', function () {
    $response = $this->getJson('/api/wallets');

    $response->assertStatus(401);
});

test('protected route with a valid token succeeds', function () {
    $user = makeUser();
    $token = auth('api')->login($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/wallets');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Refresh e logout
|--------------------------------------------------------------------------
*/

test('refresh returns a new token', function () {
    dump(config('jwt.secret')); 
    $user = makeUser();
    $token = auth('api')->login($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/refresh');

    $response->assertStatus(200)
        ->assertJsonStructure(['token']);
});

test('logout invalidates the token', function () {
    $user = makeUser();
    $token = auth('api')->login($user);

    // desloga com o token
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertStatus(200);

    // o mesmo token não deve mais funcionar
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/wallets')
        ->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Rate limiting
|--------------------------------------------------------------------------
| Após 5 tentativas em 1 minuto (throttle:5,1), a 6ª deve ser bloqueada
| com 429. Usamos credenciais inválidas para não depender de um usuário.
*/

test('login is rate limited after too many attempts', function () {
    $payload = [
        'email' => 'bruteforce@example.com',
        'password' => 'wrong-password',
    ];

    // 5 tentativas permitidas (retornam 401)
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', $payload)->assertStatus(401);
    }

    // a 6ª deve ser bloqueada pelo rate limiter
    $this->postJson('/api/login', $payload)->assertStatus(429);
});
