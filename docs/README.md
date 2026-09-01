# Documentation

Two kinds of document live here.

## How we work

| Folder | Answers | Start at |
| --- | --- | --- |
| [`guidelines/`](guidelines/README.md) | **How** any change gets from an idea to `main` — the development loop, version control, testing, code quality, CI and releasing | [`guidelines/README.md`](guidelines/README.md) |

These apply to humans and AI coding agents alike, and ship with the boilerplate as a
starting baseline. [`guidelines/README.md`](guidelines/README.md) opens with ten rules
that cover most of it. Edit them to match how your team works — or delete the folder —
but if you keep them, hold work to them.

## How this repository works

Tooling reference. The short version is in [`../AGENTS.md`](../AGENTS.md); these go
deeper.

| Document | Answers |
| --- | --- |
| [`architecture.md`](architecture.md) | The two-tree split, how the plugin autoloads, and how dependencies are scoped for release |
| [`standards.md`](standards.md) | How PHPCS and PHPStan are configured, and the settings that are easy to break |
| [`testing.md`](testing.md) | How the unit suite is wired and how to write tests that fit |
| [`workflows.md`](workflows.md) | The CI workflows and the tag-driven release pipeline |

See also [`../scripts/README.md`](../scripts/README.md) for the `./scripts/dev` CLI and
[`../tests/README.md`](../tests/README.md) for where test files go.

---

## Where your own documentation goes

The boilerplate deliberately ships no product documentation — that is yours to write.
The convention this folder assumes, and the one the guidelines reference:

| Folder | Holds |
| --- | --- |
| `spec/` | What the plugin does, in plain language, and why |
| `technical/` | How your code is organised — layering, data access, caching |
| `plan/` | What order to build it in |

Add them as you need them, and index them here.

---

## Keeping these honest

A document that describes something the code doesn't do is worse than no document. When
you change behaviour, change the document that describes it in the same pull request:

| Change | Also update |
| --- | --- |
| How we work — a process, gate or convention | The matching document in [`guidelines/`](guidelines/README.md) |
| The bootstrap, autoloading, or `scoper.inc.php` | [`architecture.md`](architecture.md) |
| A PHPCS or PHPStan setting | [`standards.md`](standards.md) |
| A CI workflow or the release build | [`workflows.md`](workflows.md) |
| Test layout, bootstraps, or PHPUnit config | [`testing.md`](testing.md), [`../tests/README.md`](../tests/README.md) |
| A supported PHP or WordPress version | [`standards.md`](standards.md#the-php-floor-is-declared-in-four-places), and the plugin header |

**One fact, one home.** If you find yourself writing something already stated elsewhere,
link to it instead. Nothing tests a document, so a fact in two places is a fact that will
eventually disagree with itself.
