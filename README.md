# Wallet Service

API REST de carteira digital construída em **Laravel 13 / PHP 8.3+** com **PostgreSQL**.

Permite criar carteiras, realizar depósitos e saques e consultar o extrato de transações.
Todas as operações financeiras são **atômicas** e o saldo é protegido contra **condições de corrida**
por meio de lock pessimista (`SELECT ... FOR UPDATE`) dentro de uma transação de banco.

[![CI](https://github.com/rkweber-max/wallet-php/actions/workflows/ci.yml/badge.svg)](https://github.com/rkweber-max/wallet-php/actions/workflows/ci.yml)

---

## Índice

- [Decisões de projeto](#decisões-de-projeto)
- [Stack](#stack)
- [Como rodar](#como-rodar)
- [Endpoints](#endpoints)
- [Comandos de console](#comandos-de-console)
- [Testes](#testes)
- [Observabilidade](#observabilidade)
- [Modelo de dados](#modelo-de-dados)
- [Estrutura do projeto](#estrutura-do-projeto)

---

## Decisões de projeto

| Decisão | Motivo |
| --- | --- |
| **Valores em centavos** (`unsigned bigint`) | Evita completamente erros de ponto flutuante. `5000` = R$ 50,00. |
| **UUID v7** como chave primária | Ordenável por tempo (bom para índice B-tree) e sem expor contagem de registros. |
| **Lock pessimista** no saldo | `lockForUpdate()` serializa operações concorrentes na mesma carteira — dois saques simultâneos não conseguem "furar" o saldo. |
| **`DB::transaction()`** envolvendo saldo + transação | Ou o saldo é atualizado **e** a transação registrada, ou nada acontece. |
| **CHECK constraints no banco** | `balance >= 0` e `amount > 0` garantidos na última camada, mesmo que a aplicação falhe. |
| **`balance_after` na transação** | Running balance: permite auditar o extrato sem recalcular a soma histórica. |
| **Transações imutáveis** | Sem `updated_at`, sem update/delete; `onDelete('restrict')` na FK da carteira. |
| **Camada de Service** | A regra de negócio vive em `WalletService` e é compartilhada pela API HTTP e pelos comandos Artisan. |

---

## Stack

- PHP 8.3+ (imagem Docker roda 8.5)
- Laravel 13.8
- PostgreSQL 16
- Nginx + PHP-FPM
- Pest 4 (testes)
- Prometheus (métricas)
- GitHub Actions (CI)

---

## Como rodar

### Pré-requisitos

Docker e Docker Compose.

### Subindo o ambiente

```bash
# 1. Configure o ambiente
cp .env.example .env

# Ajuste no .env (os valores abaixo casam com o docker-compose):
#   DB_CONNECTION=pgsql
#   DB_HOST=db
#   DB_PORT=5432
#   DB_DATABASE=wallet
#   DB_USERNAME=root
#   DB_PASSWORD=root

# 2. Suba os containers
make up          # ou: docker compose up -d

# 3. Gere a APP_KEY e rode as migrations
docker compose exec app php artisan key:generate
make migrate     # ou: docker compose exec app php artisan migrate
```

A API fica disponível em **http://localhost:8000**.

### Comandos do Makefile

| Comando | O que faz |
| --- | --- |
| `make up` | Sobe os containers |
| `make down` | Derruba os containers |
| `make build` | Rebuilda as imagens |
| `make migrate` | Roda as migrations |
| `make fresh` | Recria o banco do zero |
| `make test` | Roda a suíte de testes |
| `make shell` | Abre um shell no container da aplicação |
| `make logs` | Segue os logs da aplicação |

### Criando um usuário

A carteira exige um `user_id` de um usuário existente e o projeto não expõe endpoint de
criação de usuário. Crie um pelo Tinker:

```bash
docker compose exec app php artisan tinker
>>> App\Models\User::create(['name' => 'Test User'])->id;
```

---

## Endpoints

Base: `http://localhost:8000/api`
Especificação completa (OpenAPI 3.1): [`api/openapi.yaml`](api/openapi.yaml)

| Método | Rota | Descrição |
| --- | --- | --- |
| `POST` | `/wallets` | Cria uma carteira com saldo zero |
| `GET` | `/wallets` | Lista todas as carteiras |
| `GET` | `/wallets/{id}` | Busca uma carteira |
| `GET` | `/wallets/{id}/transactions` | Extrato da carteira |
| `POST` | `/wallets/{id}/deposit` | Deposita um valor |
| `POST` | `/wallets/{id}/withdraw` | Saca um valor |

### Exemplos

**Criar carteira**

```bash
curl -X POST http://localhost:8000/api/wallets \
  -H "Content-Type: application/json" \
  -d '{"user_id": "019f1606-46e3-7395-99e6-a87ec6140493"}'
```

```json
{
  "data": {
    "id": "019f1606-a2b8-723d-920a-70092d1a2fb9",
    "balance_in_cents": 0
  }
}
```

**Depositar R$ 50,00**

```bash
curl -X POST http://localhost:8000/api/wallets/{id}/deposit \
  -H "Content-Type: application/json" \
  -d '{"amount": 5000}'
```

**Sacar R$ 30,00**

```bash
curl -X POST http://localhost:8000/api/wallets/{id}/withdraw \
  -H "Content-Type: application/json" \
  -d '{"amount": 3000}'
```

**Extrato**

```bash
curl http://localhost:8000/api/wallets/{id}/transactions
```

```json
{
  "data": [
    {
      "id": "019f1607-1a44-7000-9c31-3f0a2f2b9d51",
      "type": "deposit",
      "amount": 5000,
      "balance_after_in_cents": 5000,
      "created_at": "2026-06-30T00:56:01.000000Z"
    }
  ]
}
```

### Erros

| Status | Quando |
| --- | --- |
| `404` | Carteira inexistente |
| `422` | Validação (`amount` ausente, não inteiro ou `< 1`; `user_id` inexistente) |
| `422` | Saldo insuficiente — `{"message": "Insufficient balance for withdrawal."}` |

Respostas em `/api/*` são sempre JSON.

---

## Comandos de console

As mesmas operações estão disponíveis via Artisan (usam o mesmo `WalletService`):

```bash
php artisan wallet:create {user_id}
php artisan wallet:deposit {wallet_id} {amount}
php artisan wallet:withdraw {wallet_id} {amount}
```

---

## Testes

```bash
make test        # ou: docker compose exec app php artisan test
```

A suíte (Pest) roda contra PostgreSQL — o banco de teste é `wallet_test`, definido em
`phpunit.xml`. Crie-o antes da primeira execução:

```bash
docker compose exec db createdb -U root wallet_test
docker compose exec app php artisan migrate --database=pgsql --force
```

Cobertura atual (`tests/Feature/WalletServiceTest.php`):

- depósito aumenta o saldo e registra a transação;
- saque diminui o saldo;
- saque acima do saldo lança `InsufficientBalanceException`;
- saque que falha não altera saldo nem cria transação;
- **race condition**: dois saques simultâneos do valor total disparados em processos
  paralelos — apenas um é concluído e uma única transação é registrada.

### CI

`.github/workflows/ci.yml` roda a suíte em todo push e PR para `main`, com PHP 8.5 e
um service container PostgreSQL 16.

---

## Observabilidade

| Rota | Descrição |
| --- | --- |
| `GET /up` | Health check padrão do Laravel |
| `GET /health` | Health check com verificação de conexão com o banco (`503` se cair) |
| `GET /metrics` | Métricas em formato Prometheus |

Métricas expostas: `deposits_total`, `withdrawals_total`, `wallets_total`.

O Prometheus sobe junto com o `docker compose` em **http://localhost:9090** e faz scrape
de `/metrics` a cada 15s (config em `docker/prometheus.yml`).

Todas as operações financeiras emitem log estruturado (`Log::info` / `Log::warning`) com
`wallet_id`, `amount`, `balance_after` e `transaction_id`.

---

## Modelo de dados

```
users                    wallets                        transactions
─────────                ───────────────────            ────────────────────────
id      uuid PK          id          uuid PK            id             uuid PK
name    varchar          balance     bigint  >= 0       type           enum(deposit, withdrawal)
timestamps               user_id     uuid FK ──┐        amount         bigint  > 0
                         timestamps            │        balance_after  bigint
                                               │        wallet_id      uuid FK ──┐
                              users.id ◄───────┘        created_at     datetime  │
                                                                                 │
                                                              wallets.id ◄───────┘
```

- `wallets.user_id` → `users.id` com `ON DELETE CASCADE`
- `transactions.wallet_id` → `wallets.id` com `ON DELETE RESTRICT` (histórico não some)
- CHECK constraints: `wallets.balance >= 0`, `transactions.amount > 0`

---

## Estrutura do projeto

```
app/
├── Console/Commands/          # wallet:create, wallet:deposit, wallet:withdraw
├── Exceptions/                # InsufficientBalanceException
├── Http/
│   ├── Controllers/           # WalletController (fino — delega ao service)
│   ├── Requests/              # Validação de entrada
│   └── Resources/             # Serialização da resposta
├── Models/                    # User, Wallet, Transaction
├── Services/                  # WalletService — toda a regra de negócio
└── TransactionType.php        # enum deposit | withdrawal

api/openapi.yaml               # Especificação da API
docker/                        # nginx.conf, prometheus.yml
tests/Feature/                 # Suíte Pest
```
