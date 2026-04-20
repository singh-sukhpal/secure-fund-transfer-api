# 💸 Secure Fund Transfer API (Symfony)

A backend API built using Symfony for securely transferring funds between accounts.
This project demonstrates enterprise-grade practices such as idempotency, concurrency control, rate limiting, and asynchronous processing.

---

## 🚀 Features

* ✅ Account creation with unique email constraint
* ✅ Secure fund transfer between accounts
* ✅ Idempotency handling using `referenceId`
* ✅ Distributed locking (Redis) to prevent race conditions
* ✅ Rate limiting per account
* ✅ Asynchronous processing using Symfony Messenger
* ✅ Transfer status tracking with caching
* ✅ Deadlock retry mechanism
* ✅ Clean architecture (Controller → Service → Repository)


---

## 🛠️ Tech Stack

* PHP 8+
* Symfony Framework
* Doctrine ORM
* Symfony Messenger (Async Queue)
* Symfony RateLimiter
* Symfony Lock Component
* MySQL
* Redis (Lock + Cache)
---

## Prerequisites

- PHP 8.1+ (match composer.json)
- Composer
- MySQL 
- Redis (for Lock & Cache)
- Docker desktop

---

## Install Redis locally
👉 Windows
Open docker desktop and make it running
- Step 1: Pull Redis image (first time only)
    Open Command Prompt / Terminal:
```bash
    docker pull redis
```
This downloads Redis into Docker.
- ▶️ Step 2: Run Redis container
```bash
docker run -d -p 6379:6379 --name redis redis
```
- ✅ Step 3: Verify Redis is running
```bash
    docker ps
```    
You should see something like:

redis   redis   Up ...

- 🧪 Step 4: Test Redis (VERY IMPORTANT)
    ```bash
        docker exec -it redis redis-cli ping
    ```
    👉 Expected output:
    
    PONG

    ✔️ This confirms Redis is working

- 📦 Step 5: Install Predis in Symfony if not

Go to your Symfony project folder:

```bash
    composer require predis/predis
```
👉 This lets PHP talk to Redis (no extension needed)

- ⚙️ Step 6: Configure .env

    Open .env and add:
```bash
    REDIS_URL=redis://127.0.0.1:6379
```
## 📦 Installation

```bash
git clone <your-repository-url>
cd paysera-transfer-api

composer install
```

## 📂 Project Structure

```
src/
 ├── Cache/          # Cache key constants (CacheKey.php)
    ├── Constants/      # API messages (ApiMessages.php)
    ├── Controller/     # HTTP endpoints (AccountController, TransferController)
    ├── Dto/            # Request DTOs (CreateAccountRequest, TransferRequest)
    ├── Entity/         # Doctrine entities (Account, Transaction)
    ├── Enum/           # Enums (TransactionStatus)
    ├── EventListener/  # Exception listener (ApiExceptionListener)
    ├── Exception/      # Custom exceptions (ApiException)
    ├── Message/        # Messenger messages (TransferMessage)
    ├── MessageHandler/ # Async handlers (TransferHandler)
    ├── Repository/     # Doctrine repositories
    └── Service/        # Business logic (TransferService, LockService, CacheService)

config/
├── packages/       # Framework config (doctrine, messenger, lock, cache, etc.)
└── services.yaml

```

---
## ⚙️ Environment Configuration

Create `.env` file:

```env
APP_ENV=dev
APP_DEBUG=1

DATABASE_URL="mysql://db_user:db_pass@127.0.0.1:3306/db_name"


# Redis for locks + cache (required if lock.yaml uses REDIS_URL)

REDIS_URL=redis://127.0.0.1:6379

# Messenger (choose one)

# Option 1: DB queue (simple)
MESSENGER_TRANSPORT_DSN=doctrine://default

# Option 2: Redis queue (recommended for production)
# MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages

```

## 🐬 MySQL Setup

### Option 1: Local MySQL

1. Start MySQL server:

- Windows (XAMPP/WAMP):
  - Open XAMPP/WAMP → Start MySQL

- macOS:
  ```bash
  brew services start mysql

2. Create database:
CREATE DATABASE paysera_db;

3. Update .env:
DATABASE_URL="mysql://root:password@127.0.0.1:3306/paysera_db"

## 🗄️ Database Setup

Prefer migrations (keeps schema consistent):

Windows PowerShell:
```bash
php bin/console doctrine:migrations:migrate
```
For a quick dev DB (sqlite):
```bash
php bin/console doctrine:schema:create
```

---

## ▶️ Run Application

Using Symfony CLI:
```bash
symfony server:start
```
Or built-in PHP server:
```bash
php -S 127.0.0.1:8000 -t public
```

Application will run at:
👉 http://127.0.0.1:8000

---


## Run the queue worker (required for async transfers)

If Messenger is configured async (recommended):
```bash
php bin/console messenger:consume async -vv
```
---
## Redis / Lock setup
Install dependencies if not:
This allows Symfony to connect to Redis without needing PHP Redis extension
```bash
composer require predis/predis
```
- Install Lock component if missing:
```bash
composer require symfony/lock
```
If you get "lock.factory not found" error: run composer install, ensure lock component is present, then clear cache using command php bin/console cache:clear

---

## 📡 API Endpoints

### 1️⃣ Create Account
**POST** `/api/account`
- 201 Created on success

#### Request

```json
{
  "email": "user@example.com",
  "balance": 100
}
```
#### Response

```json
{
  "status": "success",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "balance": "100.00"
  }
}
```

### 2️⃣ Transfer Funds

**POST** `/api/transfer-funds`
- 202 Accepted when queued
- 200 OK for idempotent replay (same reference and payload)
- 409 Conflict for duplicate reference with different payload

#### Request

```json
{
  "from": 1,
  "to": 2,
  "amount": "10.00",
  "referenceId": "txn-123"
}
```

#### Response (Queued)

```json
{
  "status": "success",
  "data": {
    "status": "queued",
    "referenceId": "txn-123"
  }
}
```

### 3️⃣ Check Transfer Status
**GET** `/api/transfer-status/{referenceId}`
- 200 OK when found, 404 Not Found otherwise

#### Response

```json
{
  "status": "success",
  "data": {
    "referenceId": "txn-123",
    "status": "SUCCESS",
    "amount": "10.00",
    "from": 1,
    "to": 2
  }
}
```

---


## HTTP semantics / Errors

- 201 Created — new account created
- 202 Accepted — transfer accepted and queued
- 200 OK — idempotent replay returning existing result
- 409 Conflict — duplicate reference with different payload OR insufficient funds depending on design
- 422 Unprocessable Entity — validation errors (response includes `errors` map)
- 500 Internal Server Error — unexpected server errors

All error responses follow the shape:
```json
{
  "status": "error",
  "message": "Human readable message",
  "errors": { /* details */ }
}
```
---


## 🧪 Testing

The project includes **integration tests** to validate API behavior including transfers, validations, idempotency, and error handling.

### ▶️ Run Tests

```bash
php bin/phpunit

---

## Troubleshooting (common issues)

- "lock.factory not found" → install symfony/lock and restart cache.
- "Cannot autowire AccountRepository" → run `php bin/console debug:autowiring App\Repository\AccountRepository` or inject ManagerRegistry instead of repository.
- Unique constraint failures on referenceId → handled by service; if you see raw DB errors ensure migrations were applied.
- If transfers remain queued, ensure messenger worker running and transport configured.

---

## 🔒 Key Design Decisions

### ✅ Idempotency

* Each transfer is identified by `referenceId`
* Same request → returns same transaction
* Different payload with same reference → rejected

---

### ✅ Concurrency Handling

* Distributed locking 
* Pessimistic database locking
* Prevents double spending and race conditions

---

### ✅ Asynchronous Processing

* Transfers are queued using Symfony Messenger
* Improves scalability and responsiveness

---
### Caching
- Transfer status cached (60s TTL) to reduce DB load.
- Cache invalidated after successful/failed transfer.
- CacheService centralizes all cache operations.

---

### ✅ Rate Limiting

* Applied per account
* Prevents abuse and excessive requests

---

### ✅ Deadlock Handling

* Automatic retry mechanism (3 attempts)

---

## ⚠️ Important Notes

* Queue worker must be running to process transfers
* Idempotency ensures safe retries
* System is designed for high concurrency scenarios

---

## 🚀 Future Improvements
* JWT Authentication / OAuth
* Webhooks for transfer completion
* Admin dashboard
* Multi-currency support
* Audit logs
* Add OpenAPI/Swagger docs 
* Load testing
* Add monitoring/metrics & structured logs (JSON)
* Add API versioning & deprecation policy

---

## 📌 How to Test Quickly

```bash
# Create account
curl -X POST http://127.0.0.1:8000/api/account \
-H "Content-Type: application/json" \
-d '{"email":"a@test.com","balance":100}'

# Transfer funds
curl -X POST http://127.0.0.1:8000/api/transfer-funds \
-H "Content-Type: application/json" \
-d '{"from":1,"to":2,"amount":"10.00","referenceId":"abc123"}'
```

---


**Time spent:** 2-3 days

---

## AI Tools & Assistance

This project was developed with assistance from **GitHub Copilot** and chatgpt.

**Prompts & tasks used:**
- How to handle request asynchronosly using event-driven architecture
- Idempotency & duplicate-reference conflict handling
- Deterministic lock ordering to prevent deadlocks
- Controller & repository patterns
- Error handling & API response standardization
- Redis / Lock configuration

---
## Author

Sukhpal Singh

