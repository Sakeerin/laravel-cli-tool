# lx — Laravel CLI Companion

[![Tests](https://github.com/your-username/lx/actions/workflows/ci.yml/badge.svg)](https://github.com/your-username/lx/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/lx/lx.svg)](https://packagist.org/packages/lx/lx)
[![Total Downloads](https://img.shields.io/packagist/dt/lx/lx.svg)](https://packagist.org/packages/lx/lx)
[![PHP Version](https://img.shields.io/packagist/php-v/lx/lx.svg)](https://packagist.org/packages/lx/lx)
[![License](https://img.shields.io/github/license/your-username/lx)](LICENSE.md)

> The CLI companion for Laravel developers that Artisan doesn't have — scaffold boilerplate, enforce conventions, check project health, and generate code with AI.

---

## Demo

### make:module — full module in one command

![make:module demo](https://raw.githubusercontent.com/your-username/lx/main/demos/make-module.gif)

### ai:migration — generate migrations from plain text *(Pro)*

![ai:migration demo](https://raw.githubusercontent.com/your-username/lx/main/demos/ai-migration.gif)

---

## Why lx?

| Feature | Artisan | `lx` |
|---|---|---|
| Convention enforcement (naming, return types) | ✗ | ✓ |
| Health check (.env, security advisories, queue config) | ✗ | ✓ |
| Module scaffold (Controller + Service + Repository at once) | partial | ✓ |
| Custom stub rules via config file | limited | ✓ |
| AI: generate migration from plain text | ✗ | ✓ Pro |
| AI: analyse error + suggest fix | ✗ | ✓ Pro |
| AI: generate Pest tests from method signature | ✗ | ✓ Pro |
| AI: code review staged diff | ✗ | ✓ Pro |

---

## Installation

### Global via Composer

```bash
composer global require lx/lx
```

Make sure Composer's global `bin` directory is in your `$PATH`:

```bash
# macOS / Linux — add to ~/.zshrc or ~/.bashrc
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### Via .phar

```bash
curl -L https://github.com/your-username/lx/releases/latest/download/lx.phar \
     -o /usr/local/bin/lx && chmod +x /usr/local/bin/lx
```

Verify:

```bash
lx --version
```

---

## Quick Start

```bash
# Scaffold a service with interface + test
lx make:service PaymentService --interface --test

# Scaffold an entire module
lx make:module Billing --all

# Lint the codebase
lx lint

# Auto-fix lint issues
lx lint --fix

# Check project health
lx check

# Initialise .lxconfig.yml
lx config:init
```

---

## AI Commands *(Pro)*

Unlock AI commands with a [Pro license](https://lx.dev/pricing) and your own Anthropic API key.

```bash
# Activate Pro license
lx license:activate LX-PRO-XXXX-XXXX-XXXX-XXXX

# Set your API key
export ANTHROPIC_API_KEY=sk-ant-...

# Generate a migration
lx ai:migration "orders table with user_id, items JSON, total decimal, status, paid_at nullable"

# Fix the last logged error
lx ai:fix --last

# Generate Pest tests for a method
lx ai:test app/Services/InvoiceService.php::calculate

# Review staged changes
lx ai:review --staged

# Monthly usage summary
lx ai:usage
```

---

## Command Reference

| Command | Description |
|---|---|
| `make:service` | Generate a Service class |
| `make:repository` | Generate a Repository class |
| `make:dto` | Generate a Data Transfer Object |
| `make:action` | Generate an Action class |
| `make:module` | Generate an entire module in one shot |
| `lint` | Lint PHP files for convention violations |
| `check` | Check project health |
| `config:init` | Create `.lxconfig.yml` interactively |
| `config:pull` | Download shared config from a URL |
| `license:activate` | Activate a Pro license key |
| `license:status` | Show license status |
| `self-update` | Update to the latest version |
| `ai:migration` | *(Pro)* Generate migration from description |
| `ai:fix` | *(Pro)* Diagnose error and suggest fix |
| `ai:test` | *(Pro)* Generate Pest/PHPUnit tests |
| `ai:review` | *(Pro)* Review staged/uncommitted changes |
| `ai:usage` | *(Pro)* Monthly token & cost summary |

Full documentation at **[lx.dev/docs](https://lx.dev/docs)**.

---

## Configuration

Create `.lxconfig.yml` at your Laravel project root to customise scaffold paths, lint rules, and more:

```yaml
scaffold:
  service_path: app/Services
  use_readonly_dto: true
  use_strict_types: true

lint:
  rules:
    require_return_types: true
    max_method_length: 30
    naming:
      service_suffix: Service

check:
  required_env:
    - APP_KEY
    - DB_CONNECTION
    - QUEUE_CONNECTION
```

See [Configuration reference](https://lx.dev/docs/configuration) for the full list.

---

## Development

```bash
git clone https://github.com/your-username/lx.git
cd lx
composer install

# Run tests
composer test

# Run a command locally
php lx make:service PaymentService --interface --test
```

### Building the .phar

```bash
php -d phar.readonly=0 box.phar compile
```

The CI pipeline builds `builds/lx.phar` automatically on every tagged release.

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines. Bug reports and feature requests go in [GitHub Issues](https://github.com/your-username/lx/issues).

---

## License

`lx` (free tier) is open source under the [MIT License](LICENSE.md).

Pro and Team AI commands are proprietary. See [Pricing](https://lx.dev/pricing).
