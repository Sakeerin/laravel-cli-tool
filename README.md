# lx

`lx` is a Laravel developer CLI companion built on Laravel Zero. This repository is currently bootstrapped for the first implementation milestone: project setup, local testing, linting, and `.phar` packaging.

## Current Setup

- Laravel Zero application scaffolded and rebranded to `lx`
- Pest test suite configured
- Laravel Pint linting scripts wired through Composer
- `box.json` included for `.phar` compilation
- GitHub Actions workflow prepared for test, lint, and build

## Local Development

```bash
composer install
composer test
composer lint
php lx list
```

## Build A PHAR

The CI workflow downloads Box and publishes a `builds/lx.phar` artifact on every successful build job.

If you already have Box available locally, compile the binary with:

```bash
php -d phar.readonly=0 box.phar compile
```
