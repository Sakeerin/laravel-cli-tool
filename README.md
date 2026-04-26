# lx

`lx` is a Laravel developer CLI companion built on Laravel Zero. It helps Laravel teams scaffold common classes quickly, keep conventions consistent, and prepare for later lint, health-check, and AI-assisted workflows.

## Features

- `make:service` with `--interface`, `--test`, `--abstract`, and `--no-constructor`
- `make:repository` with `--model`, `--interface`, and `--test`
- `make:dto` with `--readonly` and `--from-array`
- `make:action` with `--invokable` and `--test`
- `.lxconfig.yml` support for scaffold defaults such as custom class and test paths
- `.phar` packaging via Box

## Installation

For local development inside this repository:

```bash
composer install
php lx list
```

For global installation after the package is published on Packagist:

```bash
composer global require laravel-cli-tool/lx
```

## Configuration

Copy `.lxconfig.yml.example` into the root of a Laravel project as `.lxconfig.yml` to override scaffold locations and future lint/check defaults.

## Development

```bash
composer test
composer lint
php lx make:service PaymentService --interface --test
```

## Build A PHAR

The CI workflow downloads Box and publishes `builds/lx.phar` as an artifact on successful builds.

If you already have Box available locally, compile the binary with:

```bash
php -d phar.readonly=0 box.phar compile
```
