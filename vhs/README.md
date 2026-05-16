# VHS Demo Scripts

These are [VHS](https://github.com/charmbracelet/vhs) tape files for recording terminal GIFs used in README.md and the docs site.

## Prerequisites

```bash
# macOS
brew install charmbracelet/tap/vhs

# Linux
go install github.com/charmbracelet/vhs@latest
```

## Recording

Run from the project root inside a real Laravel project with `lx` installed:

```bash
# Record all demos
for tape in vhs/*.tape; do vhs "$tape"; done

# Record a single demo
vhs vhs/make-service.tape
```

GIFs are written to `demos/` (gitignored — upload to GitHub releases or a CDN).

## Tapes

| Tape | Command | Output |
|---|---|---|
| `make-service.tape` | `lx make:service PaymentService --interface --test` | `demos/make-service.gif` |
| `make-module.tape` | `lx make:module Billing --all` | `demos/make-module.gif` |
| `lint.tape` | `lx lint` + `lx lint --fix` | `demos/lint.gif` |
| `check.tape` | `lx check` | `demos/check.gif` |
| `ai-migration.tape` | `lx ai:migration "..."` | `demos/ai-migration.gif` |
| `ai-fix.tape` | `lx ai:fix "..."` | `demos/ai-fix.gif` |
| `ai-review.tape` | `lx ai:review --staged` | `demos/ai-review.gif` |

## Notes

- AI tapes require `ANTHROPIC_API_KEY` and a Pro license
- Set `Set Theme "Dracula"` for consistent look across all demos
- Recommended terminal: iTerm2 (macOS) or Ghostty
