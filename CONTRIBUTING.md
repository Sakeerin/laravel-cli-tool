# Contributing to lx

Thank you for taking the time to contribute!

## Ways to Contribute

- **Bug reports** — open a [Bug Report issue](https://github.com/your-username/lx/issues/new?template=bug_report.yml)
- **Feature requests** — open a [Feature Request issue](https://github.com/your-username/lx/issues/new?template=feature_request.yml)
- **Pull requests** — see the workflow below

---

## Development Setup

```bash
git clone https://github.com/your-username/lx.git
cd lx
composer install
```

Run the test suite:

```bash
composer test
```

Run a command locally:

```bash
php lx make:service PaymentService --interface --test
```

---

## Code Style

- PHP 8.2+ syntax
- `declare(strict_types=1)` in every file
- PSR-12 formatting — run `composer lint` to check
- Return types on all public and protected methods
- No comments except where the *why* is non-obvious

---

## Pull Request Workflow

1. **Fork** the repository and create a branch from `main`:
   ```bash
   git checkout -b feat/my-feature
   ```

2. **Write tests** — every new feature or bug fix must include tests. We use [Pest](https://pestphp.com).

3. **Run the full test suite** before opening a PR:
   ```bash
   composer test
   ```

4. **Keep PRs focused** — one feature or fix per PR. If you have multiple things to fix, open multiple PRs.

5. **Update documentation** — if you add or change a command, update or add the relevant MDX page in `docs/content/docs/`.

6. **Commit messages** — use conventional commits:
   ```
   feat: add --no-interface flag to make:repository
   fix: normalize path separators on Windows in ai:test
   docs: add ai:review example for --file option
   test: cover rate limit exceeded case in AiMigrationCommand
   ```

7. **Open the PR** — fill in the PR template and link any related issues.

---

## Adding a New Command

1. Create the command class in `app/Commands/{Category}/YourCommand.php`
2. Register it in `app/Providers/AppServiceProvider.php` under `$this->commands`
3. Write a feature test in `tests/Feature/YourCommandTest.php`
4. Add a docs page in `docs/content/docs/commands/your-command.mdx`

Use an existing command (e.g. [MakeDtoCommand](app/Commands/Make/MakeDtoCommand.php)) as a reference.

---

## Running Tests

```bash
# All tests
composer test

# Single file
./vendor/bin/pest tests/Feature/MakeServiceCommandTest.php

# With coverage (requires Xdebug or PCOV)
composer test -- --coverage
```

---

## Reporting Security Issues

Please **do not** open a public GitHub issue for security vulnerabilities. Email `security@lx.dev` instead.

---

## License

By contributing you agree that your contributions will be licensed under the [MIT License](LICENSE.md).
