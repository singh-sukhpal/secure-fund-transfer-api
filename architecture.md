# 💸 Secure Fund Transfer API - Architecture Diagram

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CLIENT LAYER                                       │
│                    (Web, Mobile, CLI, Tools)                                 │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
        ┌───────────▼──────────┐   ┌─────────▼──────────┐
        │   HTTP Requests      │   │  HTTP Requests     │
        │  (POST, GET, etc)    │   │  (POST, GET, etc)  │
        └───────────┬──────────┘   └─────────┬──────────┘
                    │                         │
└─────────────────────────────────────────────────────────────────────────────┘
│                         SYMFONY APPLICATION LAYER                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │                    ROUTING LAYER                                 │       │
│  │  (config/routes.yaml)                                            │       │
│  │  • POST /api/account          → AccountController::create()     │       │
│  │  • POST /api/transfer-funds   → TransferController::create()    │       │
│  │  • GET  /api/transfer-status  → TransferController::status()    │       │
│  └──────────────────┬───────────────────────────────────────────────┘       │
│                     │                                                        │
│  ┌──────────────────▼───────────────────────────────────────────────┐       │
│  │                    CONTROLLER LAYER                              │       │
│  │  (src/Controller/)                                               │       │
│  │                                                                  │       │
│  │  ┌─────────────────────┐      ┌─────────────────────┐           │       │
│  │  │ AccountController   │      │ TransferController  │           │       │
│  │  │ ─────────────────   │      │ ─────────────────   │           │       │
│  │  │ • create()          │      │ • create()          │           │       │
│  │  │ • getBalance()      │      │ • status()          │           │       │
│  │  └──────┬──────────────┘      └──────┬──────────────┘           │       │
│  │         │                            │                          │       │
│  │  ┌──────▼────────────────────────────▼──────┐                   │       │
│  │  │   Validation Layer (Symfony Validator)   │                   │       │
│  │  │   • DTO Validation                       │                   │       │
│  │  │   • Constraint Validation                │                   │       │
│  │  └──────┬─────────────────────────────────┬─┘                   │       │
│  │         │                                 │                     │       │
│  └─────────▼─────────────────────────────────▼─────────────────────┘       │
│            │                                 │                             │
│  ┌─────────▼──────────────────────────────────▼──────────────────────┐     │
│  │              SERVICE LAYER (Business Logic)                       │     │
│  │  (src/Service/)                                                   │     │
│  │                                                                   │     │
│  │  ┌──────────────────────────────────────────────────────────┐   │     │
│  │  │  TransferService                                         │   │     │
│  │  │  ──────────────────────────────────────────────────────  │   │     │
│  │  │  • transfer()              [Main orchestrator]           │   │     │
│  │  │  • retryTransfer()         [Deadlock retry logic]        │   │     │
│  │  │  • doTransfer()            [Atomic transfer execution]   │   │     │
│  │  │  • preValidateTransfer()   [Pre-check validation]        │   │     │
│  │  │  • resolveIdempotency()    [Idempotency check]           │   │     │
│  │  └─────┬──────────────────────────────────────────────────┬─┘   │     │
│  │        │                                                  │     │     │
│  │  ┌─────▼──────────────────┐   ┌──────────────────────────▼─┐   │     │
│  │  │  LockService           │   │  CacheService             │   │     │
│  │  │  ──────────────────    │   │  ──────────────────       │   │     │
│  │  │  • acquireTransferLock()│   │  • getTransferStatus()   │   │     │
│  │  │  • createTransferLock() │   │  • setTransferStatus()   │   │     │
│  │  │  • release()           │   │  • deleteTransferStatus()│   │     │
│  │  └─────┬──────────────────┘   └──────────┬───────────────┘   │     │
│  │        │                                 │                   │     │
│  │  ┌─────▼──────────────────┐  ┌──────────▼──────────────┐   │     │
│  │  │  AccountService        │  │  ApiResponseService     │   │     │
│  │  │  ──────────────────    │  │  ────────────────────   │   │     │
│  │  │  • createAccount()     │  │  • success()            │   │     │
│  │  │  • getBalance()        │  │  • created()            │   │     │
│  │  │  • updateBalance()     │  │  • error()              │   │     │
│  │  └────────┬───────────────┘  └──────────┬──────────────┘   │     │
│  │           │                             │                  │     │
│  └───────────▼─────────────────────────────▼──────────────────┘     │
│             │                              │                        │
│  ┌──────────▼──────────────────────────────▼──────────────────┐     │
│  │            REPOSITORY LAYER (Data Access)                 │     │
│  │  (src/Repository/)                                        │     │
│  │                                                           │     │
│  │  ┌────────────────────────┐  ┌─────────────────────────┐ │     │
│  │  │ AccountRepository      │  │ TransactionRepository   │ │     │
│  │  │ ──────────────────     │  │ ─────────────────────   │ │     │
│  │  │ • find()               │  │ • findOneByReferenceId()│ │     │
│  │  │ • findBy()             │  │ • find()                │ │     │
│  │  │ • persist()            │  │ • persist()             │ │     │
│  │  └────────────┬───────────┘  └──────────┬──────────────┘ │     │
│  │               │                         │                │     │
│  └───────────────▼─────────────────────────▼────────────────┘     │
│                  │                         │                      │
└──────────────────▼─────────────────────────▼──────────────────────┘
                   │                         │
        ┌──────────▼──────────┐   ┌──────────▼──────────┐
        │                     │   │                     │
        │   MySQL Database    │   │   Redis Cache       │
        │   ────────────────  │   │   ──────────────    │
        │   • accounts        │   │   • Locks           │
        │   • transactions    │   │   • Transfer status │
        │   • Unique indexes  │   │   • TTL management  │
        │   • Foreign keys    │   │                     │
        └─────────────────────┘   └─────────────────────┘
```

---

## Request Flow Diagram: Create Transfer

```
┌──────────────────────────────────────────────────────────────────────────┐
│  CLIENT                                                                  │
│  POST /api/transfer-funds                                                │
│  {from: 1, to: 2, amount: "10.00", referenceId: "txn-123"}              │
└────────────────┬─────────────────────────────────────────────────────────┘
                 │
                 ▼
        ┌────────────────────┐
        │ TransferController │
        │ create()           │
        └────────┬───────────┘
                 │
      ┌──────────▼──────────┐
      │ Parse & Validate    │
      │ DTO                 │
      └──────────┬──────────┘
                 │
      ┌──────────▼──────────────────────────────────────┐
      │ Pre-Validate Transfer                           │
      │ • Check accounts exist                          │
      │ • Check not same account                        │
      │ • Check balance sufficient                      │
      │ • Check idempotency (existing txn?)             │
      └──────────┬───────────────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Acquire Distributed Lock (Redis)        │
      │ key: transfer_lock_<hash>               │
      │ if locked → return 409 Conflict         │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Dispatch to Message Queue                │
      │ Symfony Messenger (Redis transport)      │
      │ Message: TransferMessage                 │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Release Lock                             │
      │ Redis: DEL transfer_lock_<hash>         │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Return 202 Accepted                      │
      │ {status: "queued", referenceId: "..."}  │
      └──────────────────────────────────────────┘


┌──────────────────────────────────────────────────────────────────────────┐
│  ASYNC QUEUE WORKER (separate process)                                   │
│  php bin/console messenger:consume async -vv                             │
└────────────────┬─────────────────────────────────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Consume Message from Queue               │
      │ TransferHandler::__invoke()              │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ TransferService::transfer()              │
      │ • Acquire Distributed Lock (Redis)       │
      │ • Retry logic (deadlock recovery)        │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ TransferService::doTransfer()            │
      │ • Begin DB transaction                   │
      │ • Fetch accounts (deterministic order)   │
      │ • Acquire Pessimistic DB locks (FOR UPD)│
      │ • Validate idempotency                   │
      │ • Check balance                          │
      │ • Debit from Account                     │
      │ • Credit to Account                      │
      │ • Create Transaction (PENDING)           │
      │ • Flush (persist changes)                │
      │ • Mark as SUCCESS                        │
      │ • Commit transaction                     │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Invalidate Cache                         │
      │ Redis: DEL transfer_status_txn-123       │
      └──────────┬──────────────────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ Release Locks & Commit DB                │
      │ • Release Distributed Lock               │
      │ • DB transaction auto-commits            │
      └──────────────────────────────────────────┘
```

---

## Data Flow Diagram: Get Transfer Status

```
┌──────────────────────────────────────────────────────────────────────────┐
│  CLIENT                                                                  │
│  GET /api/transfer-status/txn-123                                        │
└────────────────┬─────────────────────────────────────────────────────────┘
                 │
                 ▼
        ┌────────────────────────────┐
        │ TransferController         │
        │ status(referenceId)        │
        └────────┬───────────────────┘
                 │
      ┌──────────▼──────────────────────────────┐
      │ CacheService::getTransferStatus()       │
      │ key: transfer_status_txn-123            │
      └──────────┬──────────────────────────────┘
                 │
         ┌───────┴────────┐
         │                │
    Cache HIT?        Cache MISS?
         │                │
    ┌────▼─────┐   ┌──────▼─────────────────┐
    │Return    │   │Query Database          │
    │from Redis│   │TransactionRepository   │
    │(instant) │   │findOneBy()  │
    └────┬─────┘   └──────┬──────────────────┘
         │                │
         │        ┌───────▼────────┐
         │        │Build Response  │
         │        │Array           │
         │        └───────┬────────┘
         │                │
         │        ┌───────▼────────────────┐
         │        │Store in Redis          │
         │        │TTL: 60 seconds         │
         │        └───────┬────────────────┘
         │                │
         └────────┬───────┘
                  │
         ┌────────▼──────────────┐
         │Return JSON Response   │
         │200 OK or 404 Not Found│
         └───────────────────────┘
```

---

## Concurrency Control Architecture

```
┌────────────────────────────────────────────────────────────────────────────┐
│                    CONCURRENCY PROTECTION LAYERS                           │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  LAYER 1: HIGH-LEVEL IDEMPOTENCY                                          │
│  ────────────────────────────────────────────────────────────────────────  │
│                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────┐  │
│  │ Request 1: txn-123 (from:1, to:2, amt:10)                          │  │
│  ├─────────────────────────────────────────────────────────────────────┤  │
│  │ ✅ LockService acquires: lock_key_txn123 in Redis                  │  │
│  │ ✅ Enters critical section (serialized)                            │  │
│  │ ✅ Dispatcher enqueues message                                     │  │
│  │ ✅ Release lock → DEL lock_key_txn123                              │  │
│  │ ✅ Return 202 Accepted                                             │  │
│  └─────────────────────────────────────────────────────────────────────┘  │
│                            │                                               │
│                            ▼ (same reference)                             │
│  ┌─────────────────────────────────────────────────────────────────────┐  │
│  │ Request 2: txn-123 (arrives 5ms later)                             │  │
│  ├─────────────────────────────────────────────────────────────────────┤  │
│  │ ❌ LockService fails to acquire (Request 1 holds lock)             │  │
│  │ ❌ Return null → throw 409 Conflict (immediate)                    │  │
│  │ ✅ Never reaches queue (prevents duplicate processing)             │  │
│  └─────────────────────────────────────────────────────────────────────┘  │
│                                                                            │
│  LAYER 2: DATABASE-LEVEL INTEGRITY                                        │
│  ────────────────────────────────────────────────────────────────────────  │
│                                                                            │
│  When different txn references modify same accounts:                      │
│                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────┐  │
│  │ Transfer 1: Account 1 → 2 (txn-111)                                │  │
│  ├─────────────────────────────────────────────────────────────────────┤  │
│  │ 1. Lock accounts in order: min(1,2)=1, max(1,2)=2                 │  │
│  │ 2. SELECT FROM accounts WHERE id IN (1,2) FOR UPDATE              │  │
│  │ 3. Account 1: balance 100 → 90                                     │  │
│  │ 4. Account 2: balance 50 → 60                                      │  │
│  │ 5. INSERT INTO transactions (...)                                  │  │
│  │ 6. COMMIT (locks released)                                         │  │
│  └─────────────────────────────────────────────────────────────────────┘  │
│                            │                                               │
│         ┌──────────────────▼──────────────────┐                           │
│         │ Transfer 2 (txn-222) tries to run   │                           │
│         │ on same Account 1                   │                           │
│         ├───────────────────────────────────┤                           │
│         │ ⏳ WAITS for locks to be released │                           │
│         │    (deterministic order prevents   │                           │
│         │    deadlock)                       │                           │
│         └──────────────────┬─────────────────┘                           │
│                            │                                               │
│         ┌──────────────────▼──────────────────┐                           │
│         │ Transfer 1 released locks           │                           │
│         ├───────────────────────────────────┤                           │
│         │ ✅ Transfer 2 acquires locks       │                           │
│         │ ✅ Sees fresh balance (100→90)    │                           │
│         │ ✅ Proceeds with correct state     │                           │
│         └───────────────────────────────────┘                           │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## Technology Stack - Interaction Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                      APPLICATION LAYER                                   │
│  (Symfony Framework, PHP 8.1)                                            │
└────────┬────────────────────────────────────────────────────────────────┘
         │
    ┌────┴────────────────────────────────────────────┬──────────────┐
    │                                                 │              │
    ▼                                                 ▼              ▼
┌─────────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  Doctrine ORM       │  │  Symfony Messenger   │  Symfony Lock    │
│  ──────────────────  │  │  ────────────────    │  ─────────────   │
│  • Entity mapping   │  │  • Queue jobs        │  • Distributed   │
│  • Query builder    │  │  • Message handlers  │    locks (Redis) │
│  • Repositories     │  │  • Transport drivers │  • TTL/expiry    │
│  • Transactions     │  │                      │  • Atomic SET    │
└──────┬──────────────┘  └──────┬───────────────┘  └────┬──────────┘
       │                        │                       │
       ▼                        ▼                       ▼
┌────────────────┐  ┌────────────────┐  ┌────────────────┐
│   MySQL        │  │  Redis Queue   │  │  Redis Lock    │
│   ──────────   │  │  ─────────────  │  │  ───────────   │
│   • ACID        │  │  • Messages    │  │  • Keys with   │
│   • Foreign keys│  │  • Persistence │  │    TTL         │
│   • Indexes     │  │  • Consumer    │  │  • NX (atomic) │
│   • Constraints │  │    workers     │  │    SET         │
└────────────────┘  └────────────────┘  └────────────────┘
       │                        │                       │
       └────────────┬───────────┴───────────────────────┘
                    │
            ┌───────▼────────┐
            │   Persistence  │
            │   Layer        │
            └────────────────┘
```

---

## Error Handling Architecture

```
┌────────────────────────────────────────────────────────────────────────┐
│                      ERROR FLOW                                        │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Controller Layer                                                      │
│  ↓                                                                     │
│  Validation Fails → ApiException (422)                                │
│  ↓                                                                     │
│  Service Layer                                                         │
│  ├─ Account not found → ApiException (404)                            │
│  ├─ Insufficient funds → ApiException (409)                           │
│  ├─ Same account transfer → ApiException (400)                        │
│  ├─ Duplicate reference conflict → ApiException (409)                 │
│  ├─ DeadlockException → Retry 3 times                                 │
│  ├─ Lock timeout → ApiException (409)                                 │
│  └─ Generic exception → ApiException (500)                            │
│  ↓                                                                     │
│  EventListener: ApiExceptionListener                                   │
│  ├─ Catch all exceptions                                              │
│  ├─ Convert to standardized JSON response                             │
│  ├─ Set appropriate HTTP status code                                  │
│  ├─ Log error with context                                            │
│  └─ Return error response to client                                   │
│  ↓                                                                     │
│  Client receives:                                                      │
│  {                                                                      │
│    "status": "error",                                                  │
│    "message": "Human readable message",                                │
│    "errors": { /* field details */ }                                   │
│  }                                                                      │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Message Handler Flow (Async Processing)

```
┌──────────────────────────────────────────────────────────────────────────┐
│  QUEUE CONSUMER PROCESS                                                  │
│  $ php bin/console messenger:consume async -vv                          │
└──────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────────┐
                    │ TransferHandler     │
                    │ __invoke()          │
                    └─────────┬───────────┘
                              │
                    ┌─────────▼──────────────┐
                    │ Extract TransferMessage│
                    │ (from, to, amount,ref)│
                    └─────────┬──────────────┘
                              │
                    ┌─────────▼──────────────────────────┐
                    │ Call TransferService::transfer()   │
                    │ (with retry logic)                 │
                    └─────────┬──────────────────────────┘
                              │
                ┌─────────────┼─────────────┐
                │             │             │
           SUCCESS        FAILURE      DEADLOCK
                │             │             │
         ┌──────▼──┐   ┌──────▼──┐   ┌─────▼────┐
         │Transaction│   │Mark as│   │Retry    │
         │COMPLETE   │   │FAILED │   │(3 times)│
         │in DB      │   │in DB  │   │         │
         └───────────┘   └───────┘   └─────────┘
                │             │             │
         ┌──────▼─────────────▼─────────────▼───┐
         │ Remove message from queue             │
         │ (marked as handled successfully)      │
         └──────────────────────────────────────┘
```

---

## Caching Strategy

```
┌────────────────────────────────────────────────────────────────────────┐
│                    CACHING LAYERS                                      │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  GET /api/transfer-status/txn-123                                     │
│  ↓                                                                     │
│  Cache Key: 'transfer_status_txn-123'                                 │
│  ↓                                                                     │
│  ┌─────────────────────────────────┐                                  │
│  │ Check Redis Cache               │                                  │
│  └─────────────────────────────────┘                                  │
│  ↓              ↓                                                      │
│  HIT            MISS                                                   │
│  ↓              ↓                                                      │
│  ✅ Return   ┌──▼──────────────────────────┐                          │
│  from cache  │ Query TransactionRepository │                          │
│  (~1ms)      │ • SELECT * FROM transactions│                          │
│             │   WHERE referenceId = ?     │                          │
│             └──┬───────────────────────────┘                          │
│                ↓                                                       │
│             ┌──────────────────────┐                                  │
│             │ Build response array │                                  │
│             └──┬───────────────────┘                                  │
│                ↓                                                       │
│             ┌─────────────────────────────────┐                       │
│             │ Store in Redis Cache            │                       │
│             │ • SET transfer_status_txn-123   │                       │
│             │ • TTL: 60 seconds               │                       │
│             │ • Auto-expire after 60s         │                       │
│             └──┬───────────────────────────────┘                       │
│                ↓                                                       │
│             Return response                                           │
│                                                                        │
│  Cache Invalidation:                                                  │
│  After successful/failed transfer:                                   │
│  ├─ TransferService::doTransfer() completes                          │
│  ├─ CacheService::deleteTransferStatus(referenceId)                  │
│  └─ Redis: DEL transfer_status_txn-123                               │
│     (Next request queries fresh from DB)                             │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema Relationships

```
┌─────────────────────────────┐
│      accounts               │
├─────────────────────────────┤
│ id (PK)                     │ ◄──────────┐
│ email (UNIQUE)              │            │
│ balance (DECIMAL(19,2))     │            │
│ created_at                  │            │
│ updated_at                  │            │
└─────────────────────────────┘            │
           │                               │
           ▼ (from_account_id)             │ (to_account_id)
       ┌─────────────────────────────────────────────────┐
       │      transactions                              │
       ├─────────────────────────────────────────────────┤
       │ id (PK)                                        │
       │ from_account_id (FK) ──────────┐               │
       │ to_account_id (FK) ────────────┴──→ accounts  │
       │ amount (DECIMAL(19,2))                        │
       │ referenceId (UNIQUE, NOT NULL)                │
       │ status (ENUM: PENDING, SUCCESS, FAILED)       │
       │ created_at                                    │
       │ updated_at                                    │
       └─────────────────────────────────────────────────┘
       
       Indexes:
       • PRIMARY KEY (id)
       • UNIQUE (referenceId)  ← Prevents duplicates
       • FOREIGN KEY (from_account_id) → accounts(id)
       • FOREIGN KEY (to_account_id) → accounts(id)
```

---

## Deployment Architecture (Production)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PRODUCTION ENVIRONMENT                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Load Balancer (nginx / HAProxy)                                 │  │
│  │  ├─ Route requests across instances                              │  │
│  │  └─ Health checks every 30s                                      │  │
│  └────────┬─────────────────────────────────────────────────────────┘  │
│           │                                                             │
│     ┌─────┴─────┬─────────┐                                             │
│     │           │         │                                             │
│  ┌──▼──┐    ┌──▼──┐   ┌──▼──┐                                          │
│  │App1 │    │App2 │   │App3 │  ← Symfony instances (php-fpm)           │
│  └──┬──┘    └──┬──┘   └──┬──┘                                          │
│     │         │         │                                               │
│     └─────────┼─────────┘                                               │
│               │                                                         │
│       ┌───────▼─────────────────────┐                                  │
│       │  Persistent Storage Layer   │                                  │
│       ├──────────┬──────────────────┤                                  │
│       │          │                  │                                  │
│    ┌──▼──┐    ┌──▼──┐            ┌──▼──────┐                          │
│    │MySQL│    │MySQL│ (Replication)         │                          │
│    │Prim │ ──→│Repl │   ┌─────────▼───────┐ │                          │
│    └─────┘    └─────┘    │ Backup (S3/AWS)│ │                          │
│                          └─────────────────┘ │                          │
│                                             │                          │
│  ┌──────────────────────────────────────────┘                          │
│  │                                                                     │
│  │  ┌─────────────────────────────────────────────────────────────┐  │
│  │  │  Redis Cluster (High Availability)                          │  │
│  │  ├─ Primary                                                    │  │
│  │  ├─ Replica 1                                                  │  │
│  │  └─ Replica 2                                                  │  │
│  │     (Locks, Cache, Queue replication)                          │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │  Message Queue Workers (Kubernetes pods)                       │  │
│  │  ├─ Worker 1: php bin/console messenger:consume async         │  │
│  │  ├─ Worker 2: php bin/console messenger:consume async         │  │
│  │  └─ Worker N: php bin/console messenger:consume async         │  │
│  │     (Auto-scales based on queue depth)                        │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │  Monitoring & Observability                                    │  │
│  │  ├─ Prometheus metrics                                         │  │
│  │  ├─ ELK Stack (structured logs)                               │  │
│  │  ├─ Jaeger (distributed tracing)                              │  │
│  │  └─ Alerting (PagerDuty)                                      │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Request/Response Sequence Diagram

```
Client          Controller          Service           Cache         Database
  │                 │                  │                │              │
  │  POST /api/transfer-funds          │                │              │
  ├────────────────►│                  │                │              │
  │                 │  Validate DTO    │                │              │
  │                 │  & pre-check     │                │              │
  │                 ├─────────────────►│                │              │
  │                 │                  │  Get transfer  │              │
  │                 │                  │  status (check │              │
  │                 │                  │  idempotency)  │              │
  │                 │                  ├───────────────►│              │
  │                 │                  │  Cache miss    │              │
  │                 │                  │◄───────────────┤              │
  │                 │                  │  Query DB      │──────────────►
  │                 │                  │                │              │
  │                 │                  │              Data            │
  │                 │                  │◄──────────────┤              │
  │                 │                  │  Store in      │              │
  │                 │                  │  cache (60s)   │              │
  │                 │                  ├───────────────►│ SETEX        │
  │                 │                  │                │◄─────────────┤
  │                 │                  │                │              │
  │                 │  Acquire lock    │                │              │
  │                 ├─────────────────►│                │              │
  │                 │                  │  Redis lock    │              │
  │                 │                  ├───────────────►│ SET (NX)     │
  │                 │                  │                │  (atomic)    │
  │                 │                  │ OK (acquired)  │              │
  │                 │                  │◄───────────────┤              │
  │                 │                  │                │              │
  │                 │  Dispatch to     │                │              │
  │                 │  queue           │                │              │
  │                 ├─────────────────►│                │              │
  │                 │                  │ Redis LPUSH    │              │
  │                 │                  ├───────────────►│ (enqueue)    │
  │                 │                  │                │              │
  │                 │  Release lock    │                │              │
  │                 ├─────────────────►│                │              │
  │                 │                  │ Redis DEL      │              │
  │                 │                  ├───────────────►│              │
  │                 │                  │                │              │
  │  202 Accepted   │◄─────────────────┤                │              │
  │◄────────────────┤                  │                │              │
  │                 │                  │                │              │
  │                 │                  │                │              │
  ├─────────────────────────────────────────────────────────────────────►
  │               [ASYNC PROCESSING - Message Worker]                  │
  │                 │                  │                │              │
  │                 │                  │ Process        │              │
  │                 │                  │ message        │              │
  │                 │                  │ from queue     │              │
  │                 │                  ├───────────────►│ LPOP         │
  │                 │                  │                │              │
  │                 │                  │  Begin TX      │              │
  │                 │                  │  Get accounts  │──────────────►
  │                 │                  │  (deterministic)              │
  │                 │                  │                │              │
  │                 │                  │ Lock FOR UPD   │──────────────►
  │                 │                  │                │ SELECT...    │
  │                 │                  │                │ FOR UPDATE   │
  │                 │                  │                │              │
  │                 │                  │ Update balance │──────────────►
  │                 │                  │ Create TX      │ UPDATE...    │
  │                 │                  │ INSERT...      │              │
  │                 │                  │                │              │
  │                 │                  │ Commit         │──────────────►
  │                 │                  │                │ COMMIT       │
  │                 │                  │                │              │
  │                 │                  │ Cache invalid. │──────────────►
  │                 │                  ├───────────────►│ DEL (key)    │
  │                 │                  │                │              │
```

---

This architecture demonstrates:
- ✅ Separation of concerns (Controller → Service → Repository)
- ✅ Asynchronous processing (Queue workers)
- ✅ Concurrency protection (Redis lock + DB pessimistic lock)
- ✅ Caching strategy (Redis cache with TTL)
- ✅ Data integrity (ACID transactions)
- ✅ Production-ready scalability
- ✅ Error handling & monitoring ready

