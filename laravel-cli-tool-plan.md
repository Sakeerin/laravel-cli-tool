# แผนพัฒนา Laravel Dev CLI Tool (lx)

> **สถานะ:** Planning Phase  
> **เวอร์ชัน:** 1.0  
> **อัปเดตล่าสุด:** เมษายน 2026  
> **Stack:** PHP 8.3 · Laravel Zero · Claude API · Composer · .phar

---

## สารบัญ

1. [ภาพรวมโปรเจค](#1-ภาพรวมโปรเจค)
2. [Tech Stack](#2-tech-stack)
3. [สถาปัตยกรรมระบบ](#3-สถาปัตยกรรมระบบ)
4. [Command Reference](#4-command-reference)
5. [Configuration System (.lxconfig.yml)](#5-configuration-system-lxconfigyml)
6. [AI Commands Design](#6-ai-commands-design)
7. [License System](#7-license-system)
8. [Timeline แบบละเอียด (10 สัปดาห์)](#8-timeline-แบบละเอียด-10-สัปดาห์)
9. [Monetization & Pricing](#9-monetization--pricing)
10. [Go-to-Market Strategy](#10-go-to-market-strategy)
11. [Risk Assessment](#11-risk-assessment)
12. [Definition of Done](#12-definition-of-done)

---

## 1. ภาพรวมโปรเจค

### Vision
CLI tool สำหรับ Laravel developer ที่ scaffold boilerplate ได้เร็ว enforce convention ของทีม ตรวจ health ของ project และมี AI commands ช่วยสร้าง migration, fix error, generate test — เป็น companion ที่ Artisan ไม่มี ไม่ใช่ทดแทน Artisan

### สิ่งที่ทำได้ที่ Artisan ทำไม่ได้

| Feature | Artisan | `lx` |
|---|---|---|
| Convention enforcement (ชื่อ method, return type) | ✗ | ✓ |
| Health check (.env, security advisories, queue config) | ✗ | ✓ |
| Module scaffold (Controller + Service + Repository + Test ครั้งเดียว) | partial | ✓ |
| Custom stub rules via config file | limited | ✓ |
| AI: สร้าง migration จาก plain text | ✗ | ✓ (Pro) |
| AI: วิเคราะห์ error + แนะนำ fix | ✗ | ✓ (Pro) |
| AI: generate Pest test จาก method signature | ✗ | ✓ (Pro) |
| AI: code review staged diff | ✗ | ✓ (Pro) |

### Business Model: Open Core
- **Free / MIT:** command หลักทั้งหมดเป็น open source — ดึง GitHub stars และ community
- **Pro ($9/dev/เดือน):** AI commands ที่ต้องการ Claude API
- **Team ($29/5 devs/เดือน):** shared config + analytics

---

## 2. Tech Stack

### CLI Framework

```
PHP 8.3                  — runtime
Laravel Zero 10.x        — micro-framework สำหรับ CLI (Artisan-style DX)
Symfony Console 7.x      — command, argument, option parsing
```

> **ทำไม Laravel Zero:** มี Artisan-style DX คุ้นเคยอยู่แล้ว, compile เป็น .phar ได้ด้วย `box`, built-in `box()`, `table()`, `progressBar()`, รองรับ Eloquent/database ถ้าต้องการ

### Code Generation

```
nette/php-generator       — generate PHP AST → code string (type-safe)
twig/twig                 — stub templates (flexible กว่า Blade สำหรับ CLI)
friendsofphp/php-cs-fixer — format generated code ให้ตรง PSR-12
```

### Static Analysis Integration

```
phpstan/phpstan           — run + parse JSON output
squizlabs/php_codesniffer — convention lint
pestphp/pest             — run tests + parse results
```

### AI Layer

```
anthropic/anthropic-sdk-php — Claude API (official PHP SDK)
```

### Distribution

```
box/box (humbug/box)      — compile .phar binary
GitHub Actions            — build binaries สำหรับ macOS, Linux, Windows
Packagist                 — Composer distribution (free tier)
Homebrew tap              — macOS: brew install lx
```

### License Server

```
Laravel 11               — license API backend
LemonSqueezy             — billing + subscription management
Redis                    — rate limiting, license cache
```

### Development Tools

```
pestphp/pest             — unit tests สำหรับ lx เอง
mockery/mockery          — mock external services (Claude API, filesystem)
```

---

## 3. สถาปัตยกรรมระบบ

### Directory Structure

```
lx/
├── app/
│   ├── Commands/
│   │   ├── Make/
│   │   │   ├── MakeServiceCommand.php
│   │   │   ├── MakeRepositoryCommand.php
│   │   │   ├── MakeDtoCommand.php
│   │   │   ├── MakeActionCommand.php
│   │   │   └── MakeModuleCommand.php
│   │   ├── Lint/
│   │   │   └── LintCommand.php
│   │   ├── Check/
│   │   │   └── CheckCommand.php
│   │   └── Ai/
│   │       ├── AiMigrationCommand.php   (Pro)
│   │       ├── AiFixCommand.php         (Pro)
│   │       ├── AiTestCommand.php        (Pro)
│   │       └── AiReviewCommand.php      (Pro)
│   ├── Services/
│   │   ├── ScaffoldService.php          — file generation logic
│   │   ├── ConventionLinter.php         — PHP_CodeSniffer wrapper
│   │   ├── HealthChecker.php            — project health checks
│   │   ├── ClaudeService.php            — Claude API wrapper
│   │   └── LicenseService.php           — Pro license validation
│   ├── Config/
│   │   └── LxConfig.php                 — parse + validate .lxconfig.yml
│   └── Stubs/
│       ├── service.php.twig
│       ├── repository.php.twig
│       ├── dto.php.twig
│       ├── action.php.twig
│       ├── test.php.twig
│       └── module/                      — module scaffold stubs
│           ├── controller.php.twig
│           ├── service.php.twig
│           ├── migration.php.twig
│           └── routes.php.twig
├── tests/
│   ├── Unit/
│   │   ├── ScaffoldServiceTest.php
│   │   ├── ConventionLinterTest.php
│   │   ├── HealthCheckerTest.php
│   │   └── TaxEngineTest.php
│   └── Feature/
│       ├── MakeServiceCommandTest.php
│       └── LintCommandTest.php
├── .lxconfig.yml.example
├── box.json                             — .phar compile config
└── composer.json
```

### Command Lifecycle

```
User รัน: lx make:service PaymentService --interface --test
  │
  ▼
bin/lx (entry point)
  └── Application::run()
        └── MakeServiceCommand::handle()
              ├── LxConfig::load()           — โหลด .lxconfig.yml (ถ้ามี)
              ├── ScaffoldService::make()
              │     ├── resolveNamespace()   — จาก PSR-4 autoload ใน composer.json
              │     ├── renderStub()         — Twig template → PHP code string
              │     ├── PhpCsFixer::format() — format ให้ตรง PSR-12
              │     └── writeFile()          — สร้างไฟล์จริง (พร้อม mkdir -p)
              ├── output: "✓ app/Services/PaymentService.php"
              └── output: "✓ tests/Unit/Services/PaymentServiceTest.php"
```

### Namespace Resolution

```php
// ดึง namespace อัตโนมัติจาก composer.json ของ project ที่ lx รันอยู่

class NamespaceResolver
{
    public function resolve(string $path): string
    {
        $composer = json_decode(
            file_get_contents(getcwd() . '/composer.json'),
            true
        );

        // ค้นหา PSR-4 autoload ที่ match กับ path
        foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $dir) {
            if (str_starts_with($path, trim($dir, '/'))) {
                $relative = substr($path, strlen(trim($dir, '/')));
                return rtrim($namespace, '\\') . str_replace('/', '\\', $relative);
            }
        }

        return 'App\\' . str_replace('/', '\\', $path);
    }
}
```

---

## 4. Command Reference

### make:service

```bash
lx make:service {Name} [options]

Options:
  --interface    สร้าง Interface ใน app/Contracts/
  --test         สร้าง Unit Test ใน tests/Unit/Services/
  --abstract     สร้างเป็น abstract class
  --no-constructor ไม่สร้าง constructor

Example:
  lx make:service UserService --interface --test

Output:
  ✓ app/Services/UserService.php
  ✓ app/Contracts/UserServiceInterface.php
  ✓ tests/Unit/Services/UserServiceTest.php
```

**Generated Service (Stub):**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserServiceInterface;

class UserService implements UserServiceInterface
{
    public function __construct()
    {
        //
    }
}
```

---

### make:repository

```bash
lx make:repository {Name} [options]

Options:
  --model {Model}   bind กับ Model (ใส่ type hint อัตโนมัติ)
  --interface       สร้าง Interface
  --test            สร้าง Unit Test

Example:
  lx make:repository UserRepository --model=User --interface --test

Output:
  ✓ app/Repositories/UserRepository.php
  ✓ app/Contracts/UserRepositoryInterface.php
  ✓ tests/Unit/Repositories/UserRepositoryTest.php
```

---

### make:dto

```bash
lx make:dto {Name} [options]

Options:
  --readonly    สร้างเป็น readonly class (PHP 8.2+)
  --from-array  เพิ่ม static fromArray() factory method

Example:
  lx make:dto CreateUserData --readonly --from-array

Generated:
  app/Data/CreateUserData.php
```

**Generated DTO:**

```php
<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CreateUserData
{
    public function __construct(
        // TODO: add properties
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            // TODO: map properties
        );
    }
}
```

---

### make:action

```bash
lx make:action {Name} [options]

Options:
  --invokable   สร้างเป็น single __invoke() method
  --test        สร้าง Unit Test

Example:
  lx make:action CreateUserAction --invokable --test
```

---

### make:module

```bash
lx make:module {Name} [options]

Options:
  --model         สร้าง Eloquent Model
  --controller    สร้าง Controller (Resource)
  --service       สร้าง Service + Interface
  --repository    สร้าง Repository + Interface
  --policy        สร้าง Policy
  --migration     สร้าง Migration
  --seeder        สร้าง Seeder
  --test          สร้าง Feature + Unit Tests
  --all           สร้างทั้งหมด

Example:
  lx make:module Billing --all

Output:
  ✓ app/Models/Billing.php
  ✓ app/Http/Controllers/BillingController.php
  ✓ app/Services/BillingService.php
  ✓ app/Contracts/BillingServiceInterface.php
  ✓ app/Repositories/BillingRepository.php
  ✓ app/Contracts/BillingRepositoryInterface.php
  ✓ app/Policies/BillingPolicy.php
  ✓ database/migrations/2026_04_17_000000_create_billings_table.php
  ✓ database/seeders/BillingSeeder.php
  ✓ tests/Feature/BillingControllerTest.php
  ✓ tests/Unit/Services/BillingServiceTest.php
  ✓ routes/billing.php (resource routes)
```

---

### lint

```bash
lx lint [path] [options]

Options:
  --fix         auto-fix issues ที่แก้ได้
  --strict      ใช้ rules เข้มข้นกว่า default
  --format      output format: text|json|github (สำหรับ CI)

Example:
  lx lint                    # ตรวจ app/ และ tests/
  lx lint app/Services       # ตรวจ directory เดียว
  lx lint --fix              # fix อัตโนมัติ
  lx lint --format=github    # GitHub Actions annotation format

Default checks:
  - PHP_CodeSniffer PSR-12
  - Missing return types (PHP 8+)
  - Missing declare(strict_types=1)
  - Unused imports
  - เช็ค rules จาก .lxconfig.yml
```

---

### check

```bash
lx check [options]

Options:
  --fix    แนะนำ fix commands (ไม่ auto-fix)

Checks:
  Environment:
  ✓ PHP version (ตาม composer.json require)
  ✓ Laravel version (ตาม composer.lock)
  ✓ .env variables ครบตาม .env.example
  ✗ Missing: QUEUE_CONNECTION (falling back to sync)

  Security:
  ✓ composer audit (security advisories)
  ⚠ 2 packages with known vulnerabilities

  Configuration:
  ✓ APP_KEY set
  ✓ APP_DEBUG=false ใน production
  ⚠ LOG_CHANNEL=stack (แนะนำ daily หรือ stderr)

  Queue:
  ✓ QUEUE_CONNECTION=redis
  ✓ Horizon configured
```

---

### ai:migration (Pro)

```bash
lx ai:migration {description} [options]

Options:
  --table {name}   ระบุชื่อ table ชัดเจน
  --dry-run        แสดงผลโดยไม่สร้างไฟล์

Example:
  lx ai:migration "ตาราง orders มี user_id, items (json), total, status, paid_at nullable"

Output:
  Generating migration...
  ✓ database/migrations/2026_04_17_000001_create_orders_table.php

  Preview:
  - user_id (foreignId, constrained)
  - items (json)
  - total (decimal 12,2)
  - status (string, default: pending)
  - paid_at (timestamp, nullable)
  + indexes: user_id, status
```

---

### ai:fix (Pro)

```bash
lx ai:fix {error_message} [options]
lx ai:fix --last       # อ่าน error จาก laravel.log ล่าสุด

Example:
  lx ai:fix "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'paid_amount'"

Output:
  Analyzing error...

  Diagnosis: Column 'paid_amount' does not exist in the database.

  Possible causes:
  1. Migration ยังไม่ถูกรัน
  2. ชื่อ column ผิด (อาจเป็น 'amount' หรือ 'total_amount')
  3. อยู่ใน migration ที่ยังไม่ได้ create

  Suggested fix:
  > php artisan migrate (ถ้า migration มีแล้ว)
  > lx ai:migration "add paid_amount to invoices" (ถ้ายังไม่มี)

  Run fix? [y/N]
```

---

### ai:test (Pro)

```bash
lx ai:test {file}[::{method}] [options]

Options:
  --pest     generate Pest syntax (default)
  --phpunit  generate PHPUnit syntax
  --dry-run  แสดงผลโดยไม่สร้างไฟล์

Example:
  lx ai:test app/Services/InvoiceService.php::calculate

Output:
  Reading InvoiceService::calculate...
  Generating tests...
  ✓ tests/Unit/Services/InvoiceServiceTest.php

  Generated 8 test cases:
  - it calculates subtotal correctly
  - it applies vat at 7 percent
  - it calculates wht at 3 percent
  - it applies discount before vat
  - it handles zero discount
  - it handles zero vat
  - it handles combined vat and wht
  - it throws exception for negative amount
```

---

### ai:review (Pro)

```bash
lx ai:review [options]

Options:
  --staged     review staged changes (git diff --cached)
  --diff       review uncommitted changes (git diff)
  --file {path} review ไฟล์เดียว

Example:
  lx ai:review --staged

Output:
  Reviewing 3 changed files...

  app/Http/Controllers/UserController.php
  ⚠ Line 42: Potential N+1 query — ควร eager load relationships
    Suggestion: User::with('orders')->paginate()

  app/Services/UserService.php
  ✓ No issues found

  database/migrations/2026_04_17_create_users_table.php
  ✓ Migration looks correct

  Summary: 1 warning, 0 errors
```

---

## 5. Configuration System (.lxconfig.yml)

```yaml
# .lxconfig.yml — วางที่ root ของ Laravel project

version: 1

# Scaffold defaults
scaffold:
  service_path: app/Services
  repository_path: app/Repositories
  contract_path: app/Contracts
  dto_path: app/Data
  action_path: app/Actions
  test_path: tests/Unit
  use_readonly_dto: true          # default สร้าง DTO เป็น readonly
  use_strict_types: true          # inject declare(strict_types=1)

# Convention rules
lint:
  rules:
    require_return_types: true
    require_strict_types: true
    require_docblock_for: []       # ['public', 'protected'] หรือ []
    max_method_length: 30          # บรรทัด (0 = ไม่จำกัด)
    naming:
      service_suffix: Service      # ClassName ต้องลงท้ายด้วย Service
      repository_suffix: Repository
      dto_suffix: Data
  ignore:
    - app/Http/Controllers/Auth    # ข้าม directory นี้
    - "**/*Request.php"            # glob pattern

# Health check config  
check:
  required_env:
    - APP_KEY
    - DB_CONNECTION
    - QUEUE_CONNECTION
    - CACHE_DRIVER
  warn_if:
    - key: APP_DEBUG
      value: "true"
      message: "APP_DEBUG should be false in production"
    - key: QUEUE_CONNECTION
      value: sync
      message: "Use redis or database queue in production"

# AI commands (Pro) — ดึง key จาก env โดย default
ai:
  model: claude-sonnet-4-6
  max_tokens: 4096
  migration_style: fluent          # fluent | schema_builder
  test_framework: pest             # pest | phpunit
  review_focus:
    - n_plus_one
    - missing_indexes
    - security
    - naming_convention
```

---

## 6. AI Commands Design

### ClaudeService

```php
// app/Services/ClaudeService.php

use Anthropic\Client;

class ClaudeService
{
    private Client $client;

    public function __construct(private readonly LicenseService $license)
    {
        $this->license->requirePro();   // throw ถ้าไม่มี Pro license

        $this->client = Anthropic::client(
            config('lx.anthropic_api_key') ?? $_ENV['ANTHROPIC_API_KEY'] ?? ''
        );
    }

    /**
     * Streaming response สำหรับ real-time output ใน terminal
     */
    public function streamText(string $system, string $prompt, callable $onChunk): string
    {
        $full = '';

        $stream = $this->client->messages()->createStreamed([
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 4096,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta') {
                $chunk = $event->delta->text ?? '';
                $full .= $chunk;
                $onChunk($chunk);
            }
        }

        return $full;
    }

    /**
     * Non-streaming สำหรับ structured output (JSON)
     */
    public function complete(string $system, string $prompt): string
    {
        $response = $this->client->messages()->create([
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 4096,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $response->content[0]->text;
    }
}
```

### System Prompts

```php
// app/Prompts/MigrationPrompt.php

class MigrationPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
        You are a Laravel expert. Generate a Laravel migration file based on the user's description.

        Rules:
        - Use Laravel 11 migration syntax
        - Add appropriate indexes for foreign keys and commonly filtered columns
        - Use nullable() for optional fields
        - Use appropriate column types: string, text, integer, decimal, boolean, json, timestamp
        - Add $table->timestamps() unless told otherwise
        - Wrap in Schema::create() block
        - Output ONLY the PHP migration file content, no explanation

        Output format: valid PHP code only, starting with <?php
        PROMPT;
    }

    public static function user(string $description): string
    {
        return "Create a migration for: {$description}";
    }
}
```

---

## 7. License System

### ระบบ License Key

```
User ซื้อ Pro ผ่าน LemonSqueezy
  │
  ├── LemonSqueezy webhook → Laravel License API
  │     └── สร้าง license key รูปแบบ: LX-PRO-XXXX-XXXX-XXXX-XXXX
  │
  └── User รัน: lx license:activate LX-PRO-XXXX-XXXX-XXXX-XXXX
        ├── เรียก License API ตรวจสอบ key
        ├── บันทึก key + expiry ใน ~/.lx/license.json (encrypted)
        └── ✓ Pro features unlocked

Online validation (ทุกครั้งที่รัน AI command):
  ├── อ่าน key จาก ~/.lx/license.json
  ├── เช็ค cache: ถ้า validated ใน 24 ชั่วโมงที่ผ่านมา → ผ่านทันที
  ├── เรียก API validate
  │     ├── valid → cache result, proceed
  │     └── invalid → แจ้ง error
  └── Offline grace period: ถ้า API timeout → ใช้ cached validation ได้ 7 วัน
```

### LicenseService

```php
// app/Services/LicenseService.php

class LicenseService
{
    private const LICENSE_FILE = '.lx/license.json';
    private const CACHE_TTL    = 86400;     // 24 ชั่วโมง
    private const GRACE_PERIOD = 604800;    // 7 วัน

    public function requirePro(): void
    {
        if (!$this->isProActive()) {
            throw new \RuntimeException(
                "This command requires lx Pro.\n" .
                "Activate with: lx license:activate <KEY>\n" .
                "Purchase at: https://lx.dev/pro"
            );
        }
    }

    public function isProActive(): bool
    {
        $license = $this->readLicense();
        if (!$license) return false;

        // ใช้ cache ถ้ายังใหม่อยู่
        if ($this->isCacheValid($license)) return true;

        // ตรวจ online
        try {
            $valid = $this->validateOnline($license['key']);
            $this->updateCache($license, $valid);
            return $valid;
        } catch (\Exception) {
            // Offline grace period
            $lastValidated = $license['last_validated_at'] ?? 0;
            return (time() - $lastValidated) < self::GRACE_PERIOD;
        }
    }

    private function validateOnline(string $key): bool
    {
        $response = \Http::timeout(5)->post('https://api.lx.dev/license/validate', [
            'key'        => $key,
            'hostname'   => gethostname(),
            'version'    => config('app.version'),
        ]);

        return $response->successful() && $response->json('valid') === true;
    }

    private function readLicense(): ?array
    {
        $path = $_SERVER['HOME'] . '/' . self::LICENSE_FILE;
        if (!file_exists($path)) return null;
        return json_decode(file_get_contents($path), true);
    }

    private function isCacheValid(array $license): bool
    {
        return isset($license['cached_at'])
            && (time() - $license['cached_at']) < self::CACHE_TTL
            && ($license['cache_valid'] ?? false) === true;
    }
}
```

### License API (Laravel Backend)

```php
// routes/api.php (license server)

Route::post('/license/validate', [LicenseController::class, 'validate']);
Route::post('/license/activate', [LicenseController::class, 'activate']);

// LicenseController::validate
public function validate(Request $request): JsonResponse
{
    $license = License::where('key', $request->key)
        ->where('status', 'active')
        ->where('expires_at', '>', now())
        ->first();

    if (!$license) {
        return response()->json(['valid' => false, 'reason' => 'invalid_or_expired']);
    }

    // บันทึก activation log (machine count)
    $license->activations()->updateOrCreate(
        ['hostname' => $request->hostname],
        ['last_seen_at' => now(), 'version' => $request->version]
    );

    return response()->json(['valid' => true, 'plan' => $license->plan]);
}
```

---

## 8. Timeline แบบละเอียด (10 สัปดาห์)

---

### Phase 1: CLI Foundation + Core Make Commands (สัปดาห์ 1–2)

**เป้าหมาย:** `make:service`, `make:repository`, `make:dto`, `make:action` ทำงานได้ publish Packagist

#### สัปดาห์ 1 — Bootstrap + make:service

**วันที่ 1–2: Project Setup**
- [x] `composer create-project laravel-zero/laravel-zero lx`
- [x] ตั้ง binary name: `lx` ใน `composer.json`
- [x] GitHub repo + Actions workflow: test → lint → build .phar
- [x] Pest unit test setup
- [x] `box.json` สำหรับ compile .phar

**วันที่ 3–4: Stub Template System**
- [x] ติดตั้ง `twig/twig` + `friendsofphp/php-cs-fixer`
- [x] `ScaffoldService::renderStub()` — Twig render + PHP-CS-Fixer format
- [x] `NamespaceResolver::resolve()` — อ่าน PSR-4 จาก `composer.json`
- [x] `ScaffoldService::writeFile()` — สร้างไฟล์ + `mkdir -p`
- [x] stub templates: `service.php.twig`, `interface.php.twig`, `test.php.twig`

**วันที่ 5: make:service Command**
- [x] `MakeServiceCommand` — parse arguments + options
- [x] options: `--interface`, `--test`, `--abstract`, `--no-constructor`
- [x] output: colored success/error messages
- [x] unit test: `MakeServiceCommandTest` ครอบทุก option combination

**Deliverable สัปดาห์ 1:** `lx make:service PaymentService --interface --test` ทำงานได้

---

#### สัปดาห์ 2 — make:repository, dto, action + Packagist

**วันที่ 6–7: make:repository + make:dto**
- [x] `repository.php.twig` stub (พร้อม Model type hint)
- [x] `MakeRepositoryCommand` + `--model`, `--interface`, `--test`
- [x] `dto.php.twig` stub (readonly class + fromArray)
- [x] `MakeDtoCommand` + `--readonly`, `--from-array`
- [x] unit tests ทั้งหมด

**วันที่ 8: make:action**
- [ ] `action.php.twig` (invokable + regular)
- [ ] `MakeActionCommand` + `--invokable`, `--test`

**วันที่ 9–10: Publish + CI**
- [ ] `LxConfig::load()` — อ่าน `.lxconfig.yml` ถ้ามี, ใช้ defaults ถ้าไม่มี
- [ ] `composer.json` ครบ: description, keywords, license (MIT)
- [ ] publish to Packagist: `composer global require yourname/lx`
- [ ] GitHub Actions: test on PHP 8.2, 8.3 × Laravel 10, 11
- [ ] CHANGELOG.md, README.md เบื้องต้น

**Deliverable สัปดาห์ 2:** v0.1.0 บน Packagist ✅

---

### Phase 2: Convention Lint + Health Check + make:module (สัปดาห์ 3–4)

**เป้าหมาย:** `lx lint`, `lx check`, `lx make:module` พร้อมใช้งาน

#### สัปดาห์ 3 — Convention Linter

**วันที่ 11–12: ConventionLinter**
- [ ] ติดตั้ง `squizlabs/php_codesniffer`
- [ ] `ConventionLinter::run()` — PHP_CodeSniffer wrapper
- [ ] parse JSON output → structured errors
- [ ] auto-fix via `phpcbf`

**วันที่ 13–14: LintCommand**
- [ ] `LintCommand` + `--fix`, `--strict`, `--format`
- [ ] `--format=github` → GitHub Actions annotation format
- [ ] อ่าน ignore patterns จาก `.lxconfig.yml`
- [ ] แสดง progress bar ระหว่าง scan
- [ ] unit test: mock PHP_CodeSniffer output

**วันที่ 15: Custom Rules Engine**
- [ ] ตรวจ `require_return_types`, `require_strict_types` จาก `.lxconfig.yml`
- [ ] ตรวจ naming convention (suffix rules)
- [ ] ตรวจ method length

**Deliverable สัปดาห์ 3:** `lx lint --fix` ทำงานได้ ใช้ใน CI ได้

---

#### สัปดาห์ 4 — Health Check + make:module

**วันที่ 16–17: HealthChecker**
- [ ] `HealthChecker` checks:
  - PHP version vs `composer.json` require
  - .env variables ครบตาม `.env.example`
  - `composer audit` security advisories (parse JSON output)
  - `APP_DEBUG`, `APP_KEY`, `QUEUE_CONNECTION` warnings
  - `APP_ENV` ≠ production ใน production server
- [ ] `CheckCommand` + `--fix` (แสดง fix commands แนะนำ)

**วันที่ 18–19: make:module**
- [ ] module stub templates: controller, service, repository, policy, migration, seeder, routes, tests
- [ ] `MakeModuleCommand` + `--all` flag
- [ ] สร้างไฟล์ทั้งหมดใน transaction (rollback ถ้า error กลางทาง)
- [ ] สร้าง `routes/{module}.php` พร้อม resource routes

**วันที่ 20: .lxconfig.yml Full Support**
- [ ] validation ครบทุก field
- [ ] error messages ชัดเจนเมื่อ config ผิด format
- [ ] `lx config:init` — สร้าง `.lxconfig.yml` ด้วย wizard
- [ ] shared config via URL: `lx config:pull https://example.com/lxconfig.yml`

**Deliverable สัปดาห์ 4:** v0.2.0 — core feature ครบ

---

### Phase 3: AI Commands (Pro) (สัปดาห์ 5–6)

**เป้าหมาย:** AI commands ทั้ง 4 ทำงาน ใช้ ANTHROPIC_API_KEY จาก env

#### สัปดาห์ 5 — ai:migration + ai:fix

**วันที่ 21–22: Claude Service**
- [ ] ติดตั้ง `anthropic/anthropic-sdk-php`
- [ ] `ClaudeService::streamText()` — streaming output ใน terminal
- [ ] `ClaudeService::complete()` — non-streaming สำหรับ structured output
- [ ] error handling: API key ไม่มี, rate limit, timeout

**วันที่ 23–24: ai:migration**
- [ ] `AiMigrationCommand` — รับ description, ส่งไป Claude, save file
- [ ] `MigrationPrompt::system()` + `::user()`
- [ ] parse Claude output → validate เป็น PHP syntax ก่อน save
- [ ] `--dry-run` flag: แสดงผลโดยไม่สร้างไฟล์
- [ ] test ด้วย mock Claude response

**วันที่ 25: ai:fix**
- [ ] `AiFixCommand` — รับ error message, วิเคราะห์, แสดง options
- [ ] `--last` flag: อ่าน error จาก `storage/logs/laravel.log` ล่าสุด
- [ ] interactive prompt: "Run fix? [y/N]"

**Deliverable สัปดาห์ 5:** ai:migration + ai:fix ทำงานได้

---

#### สัปดาห์ 6 — ai:test + ai:review

**วันที่ 26–27: ai:test**
- [ ] `AiTestCommand` — parse method signature จากไฟล์จริง (PHP reflection)
- [ ] `TestGenerationPrompt` — include method signature + return type ใน prompt
- [ ] generate Pest syntax (default) หรือ PHPUnit (`--phpunit`)
- [ ] validate generated test เป็น PHP syntax ก่อน save

**วันที่ 28–29: ai:review**
- [ ] `AiReviewCommand` — `--staged` (git diff --cached), `--diff`, `--file`
- [ ] run `git diff` → parse output → ส่งไป Claude ทีละ file
- [ ] format output: file path + line number + severity + message
- [ ] streaming output เพื่อแสดงผล review ทีละ file

**วันที่ 30: Rate Limiting + Cost Display**
- [ ] แสดง token ที่ใช้หลัง AI command: "Used 1,240 tokens (~$0.004)"
- [ ] local rate limit: max 10 AI calls/นาที (ป้องกัน runaway loop)
- [ ] `lx ai:usage` — สรุป token ที่ใช้ใน 30 วันที่ผ่านมา

**Deliverable สัปดาห์ 6:** AI commands ครบ 4 ตัว

---

### Phase 4: License System + Distribution (สัปดาห์ 7–8)

**เป้าหมาย:** Pro tier พร้อมขาย ติดตั้งง่ายทุก platform

#### สัปดาห์ 7 — License Server

**วันที่ 31–32: License API (Laravel Backend)**
- [ ] Laravel project แยกสำหรับ license server
- [ ] Migration: `licenses`, `license_activations`
- [ ] `POST /license/validate` + `POST /license/activate`
- [ ] LemonSqueezy webhook → auto-create license on purchase

**วันที่ 33–34: LicenseService (ใน lx)**
- [ ] `LicenseService::requirePro()`, `isProActive()`
- [ ] บันทึก license ใน `~/.lx/license.json`
- [ ] online validation + 24h cache + 7-day grace period
- [ ] `lx license:activate {key}` command
- [ ] `lx license:status` command

**วันที่ 35: Guard Pro Commands**
- [ ] inject `LicenseService` เข้าทุก AI command
- [ ] error message ชัดเจนเมื่อ license ไม่ valid + link ซื้อ

**Deliverable สัปดาห์ 7:** Pro license system ทำงานครบ

---

#### สัปดาห์ 8 — Distribution + Auto-update

**วันที่ 36–37: .phar Build**
- [ ] `box.json` config: exclude dev deps, include stubs + fonts
- [ ] GitHub Actions: build .phar บน tag push
- [ ] upload binary ไป GitHub Releases อัตโนมัติ
- [ ] ทดสอบ .phar บน macOS, Linux, Windows

**วันที่ 38–39: Homebrew Tap**
- [ ] สร้าง `homebrew-lx` repo
- [ ] Formula: `lx.rb` ดึง .phar จาก GitHub Releases
- [ ] `brew install yourname/lx/lx`
- [ ] GitHub Actions: auto-update Formula เมื่อมี release ใหม่

**วันที่ 40: Auto-update Mechanism**
- [ ] `lx self-update` command
- [ ] เช็ค GitHub Releases API หา version ใหม่
- [ ] download + replace binary ตัวเอง
- [ ] `lx --version` แสดง version + "update available" ถ้ามี

**Deliverable สัปดาห์ 8:** ติดตั้งได้ผ่าน Composer global, Homebrew, direct download

---

### Phase 5: Docs + Launch (สัปดาห์ 9–10)

**เป้าหมาย:** docs ครบ GitHub ready, public launch

#### สัปดาห์ 9 — Documentation Site

**วันที่ 41–42: Docs Site (Fumadocs)**
- [ ] Next.js + Fumadocs setup: `lx.dev`
- [ ] Pages: Getting Started, Commands Reference, Configuration, Pro/AI Commands
- [ ] Code examples สำหรับทุก command
- [ ] Dark mode

**วันที่ 43–44: Demo Assets**
- [ ] Record terminal GIFs ด้วย `vhs` สำหรับทุก command หลัก
- [ ] embed ใน README.md และ docs site
- [ ] Pricing page: Free vs Pro vs Team

**วันที่ 45: GitHub Repo Polish**
- [ ] README.md ครบ: badges, demo GIF, installation, quick start
- [ ] CONTRIBUTING.md
- [ ] Issue templates: bug report, feature request
- [ ] GitHub Discussions เปิดสำหรับ community

**Deliverable สัปดาห์ 9:** docs site live, repo พร้อม public

---

#### สัปดาห์ 10 — Beta + Launch

**วันที่ 46–47: Beta Testing**
- [ ] invite 10–20 Laravel devs ทดสอบ (จาก network + Thai Laravel community)
- [ ] collect feedback ใน GitHub Discussions
- [ ] fix critical bugs

**วันที่ 48–49: Launch Prep**
- [ ] Laravel News submission (laravel-news.com)
- [ ] ProductHunt: prepare tagline, images, first comment
- [ ] Twitter/X thread: เล่าเรื่อง "ทำไมถึงสร้าง lx"
- [ ] Dev.to / Hashnode post: technical deep-dive

**วันที่ 50: Launch**
- [ ] ProductHunt launch (00:01 PST)
- [ ] โพสต์ใน Laracasts Forum, Laravel.io
- [ ] โพสต์ใน Facebook Group: Laravel Thailand
- [ ] Monitor: GitHub stars, Packagist downloads, license signups

**Deliverable สัปดาห์ 10:** v1.0.0 public launch 🚀

---

## 9. Monetization & Pricing

### Pricing

| Tier | ราคา | สิ่งที่ได้ |
|---|---|---|
| **Free (MIT)** | $0 | make commands, lint, check, custom stubs |
| **Pro** | $9/dev/เดือน | AI commands ทั้งหมด |
| **Team** | $29/5 devs/เดือน | Pro + shared config + analytics |
| **Annual Pro** | $79/ปี (ประหยัด $29) | — |
| **Annual Team** | $249/ปี | — |

### Unit Economics

```
Pro user:
  Revenue:                $9/เดือน
  Claude API cost:        ~$1.20/เดือน  (avg 30 AI calls × $0.04)
  LemonSqueezy fee:       ~$0.90/เดือน  (2.5% + $0.50 per transaction)
  Net per Pro user:       ~$6.90/เดือน

Infra cost (คงที่):
  License server VPS:     $12/เดือน
  Domain + SSL:           $2/เดือน
  Docs hosting (Vercel):  $0
  Total infra:            ~$14/เดือน

Break-even:              ~3 Pro users ($27 > $14)
```

### Revenue Projection (ปีแรก)

| เดือน | GitHub Stars | Packagist DL/เดือน | Pro | Team | MRR |
|---|---|---|---|---|---|
| 1–2 | 200 | 500 | 10 | 2 | $148 |
| 3–4 | 800 | 2K | 40 | 8 | $592 |
| 5–6 | 2K | 6K | 100 | 20 | $1,480 |
| 7–9 | 5K | 15K | 200 | 40 | $2,960 |
| 10–12 | 10K | 30K | 350 | 70 | $5,180 |

> ARR ปีแรก: **~$30,000–$50,000** (passive income หลัง launch)

### Conversion Funnel

```
Free user install (Composer) → ใช้ make commands เป็นประจำ
  → เจอ AI command ที่น่าสนใจ → ลอง run → "requires Pro license"
  → ดู pricing page → subscribe Pro $9/เดือน

Key metric: Free → Pro conversion rate target = 3–5%
```

---

## 10. Go-to-Market Strategy

### Distribution Channels (ลำดับสำคัญ)

**1. Packagist (organic, ตลอดไป)**
- `composer global require yourname/lx`
- SEO: package description ใส่ keywords: "laravel cli", "laravel scaffolding", "laravel dev tools"

**2. Laravel Ecosystem (launch week)**
- Laravel News article/submission
- Laracasts Forum
- Laravel.io
- GitHub: awesome-laravel list PR

**3. Developer Twitter/X + Dev.to**
- Thread: "I built a CLI tool that saves me 30 min/day on Laravel projects"
- Demo GIFs ของแต่ละ command
- Tag @taylorotwell (Laravel creator)

**4. Thai Laravel Community**
- Facebook Group: Laravel Thailand
- LINE Group dev ไทย

**5. ProductHunt**
- launch วันอังคาร (traffic สูงสุด)
- prepare hunter, first comment, gallery

### Positioning Statement
> "lx — the CLI companion for Laravel devs that Artisan doesn't have: convention enforcement, health checks, and AI-powered code generation"

---

## 11. Risk Assessment

### ความเสี่ยงสูง

**Artisan perception — "ทำไมไม่ใช้ Artisan?"**
- ปัญหา: dev อาจไม่เข้าใจ value proposition ทันที
- แนวทาง: demo GIF แสดงความต่างชัดเจน, focus marketing บน pain points จริง (convention drift, health check, AI commands)

### ความเสี่ยงกลาง

**ต้อง maintain ตาม Laravel version**
- แนวทาง: CI test matrix ครอบ Laravel 10, 11, 12 + PHP 8.2, 8.3 ทำงานตลอด, semantic versioning ชัดเจน

**Free tier ดีเกินไป → conversion ต่ำ**
- แนวทาง: free tier ต้องดีพอให้ติดใจ แต่ AI commands ต้องน่าสนใจพอให้ upgrade ทดสอบ conversion rate จาก beta ก่อน pricing จริง

### ความเสี่ยงต่ำ

**License key piracy**
- ยอมรับได้ — free tier ดึง community ชดเชย leakage บางส่วน

**Claude API price change**
- แนวทาง: track cost per user, ปรับ pricing ถ้าจำเป็น

---

## 12. Definition of Done

### Launch Checklist

**Core Commands**
- [ ] `make:service` + `--interface` + `--test` ทำงานถูกต้อง
- [x] `make:repository` + `--model` ทำงานถูกต้อง
- [x] `make:dto` + `--readonly` + `--from-array` ทำงานถูกต้อง
- [ ] `make:action` + `--invokable` ทำงานถูกต้อง
- [ ] `make:module --all` สร้างไฟล์ครบ 10+ ไฟล์ถูกต้อง
- [ ] `lint` + `--fix` ทำงานถูกต้อง
- [ ] `check` ตรวจครบทุก category

**AI Commands (Pro)**
- [ ] `ai:migration` สร้าง migration PHP ที่ valid ได้
- [ ] `ai:fix` วิเคราะห์ error message ได้อย่างน้อย 80% ของ common errors
- [ ] `ai:test` generate Pest test ที่ runnable ได้
- [ ] `ai:review --staged` review diff ได้โดยไม่ crash

**Infrastructure**
- [ ] `composer global require` ทำงานบน macOS, Linux, Windows
- [ ] `brew install` ทำงานบน macOS
- [ ] `.phar` binary รันได้โดยไม่ต้อง install deps
- [ ] `lx self-update` ทำงาน
- [ ] Pro license activate/validate/grace period ทำงานถูกต้อง

**Quality**
- [ ] unit test coverage > 80%
- [ ] test matrix pass: PHP 8.2, 8.3 × Laravel 10, 11
- [ ] README.md มี demo GIF ทุก command หลัก
- [ ] docs site live

---

## หมายเหตุสำหรับ Developer

### Environment Variables

```bash
# lx CLI (สำหรับ AI commands — user ตั้งค่าเอง)
ANTHROPIC_API_KEY=sk-ant-...

# License server (Laravel backend)
LX_LICENSE_API_URL=https://api.lx.dev
APP_KEY=
DB_CONNECTION=mysql
REDIS_HOST=redis
LEMON_SQUEEZY_API_KEY=
LEMON_SQUEEZY_WEBHOOK_SECRET=
```

### Key Design Decisions

| Decision | ทางเลือก | เหตุผล |
|---|---|---|
| Laravel Zero แทน raw Symfony Console | Symfony Console | Artisan-style DX, built-in output helpers, คุ้นเคยอยู่แล้ว |
| Twig แทน Blade สำหรับ stubs | Blade | Twig รัน standalone นอก Laravel project ได้, no IoC container needed |
| PHP-CS-Fixer ใน pipeline | manual formatting | generated code format สม่ำเสมอทุกครั้ง |
| MIT license สำหรับ free tier | proprietary | viral distribution, GitHub stars, community contributions |
| LemonSqueezy แทน Stripe | Stripe | built-in EU VAT handling, simpler merchant of record |
| ~/.lx/ directory | project-level storage | license เป็น per-machine ไม่ใช่ per-project |
| 7-day grace period offline | no offline | dev environment ไม่ได้ online ตลอดเวลา |

---

*review เอกสารนี้ทุก 2 สัปดาห์ และ update checklist ให้ตรงกับ implementation จริง*
