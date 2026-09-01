# Engineering guidelines

**How we work.** Not how the tooling is wired ([`../architecture.md`](../architecture.md),
[`../testing.md`](../testing.md), [`../workflows.md`](../workflows.md)) — how any change
gets from an idea to `main` without breaking the things around it.

These ship with the boilerplate as a starting baseline. Keep them, edit them to match
how your team actually works, or delete the folder — but if you keep them, hold work to
them, because a guideline nobody enforces is worse than none.

These apply equally to humans and to AI coding agents. An agent working in this
repository is expected to have read them; a reviewer is entitled to reject work that
ignores them.

| Document | Read it when |
| --- | --- |
| [01-workflow.md](01-workflow.md) | Starting any task. The loop, and what "done" means |
| [02-version-control.md](02-version-control.md) | Branching, committing, opening a PR, or rewriting history |
| [03-testing.md](03-testing.md) | Writing or changing a test — and before writing the code it covers |
| [04-code-quality.md](04-code-quality.md) | Writing plugin code |
| [05-ci-cd.md](05-ci-cd.md) | A CI gate fails, or you are cutting a release |

---

## The ten rules

Everything else in this folder is elaboration. If you only remember one screen, remember
this one.

1. **Never commit to `main`.** Branch, then open a pull request.
2. **One concern per branch, one concern per commit.** If the subject line needs an
   "and", it is two commits.
3. **Write the test before the code it covers**, or immediately after — never "later".
   A feature without a test is not finished, it is abandoned in progress.
4. **A test that cannot fail is not a test.** Before you commit it, break the code and
   watch it go red. See [03-testing.md](03-testing.md#the-mutation-check).
5. **Never weaken a gate to make it pass.** Fix the cause. If a gate genuinely must be
   disabled, say so in the code, in the commit, and in the docs.
6. **Change the docs in the same PR as the behaviour they describe.** A document
   describing something the code does not do is worse than no document.
7. **Never rewrite shared history without saying so** — and never without
   `--force-with-lease` and a verified backup.
8. **Never commit generated artifacts**: `vendor/`, `plugin/vendor-prefixed/`,
   `plugin/composer.lock`, `node_modules/`, build output, caches.
9. **Deliver the scope asked for.** Do not quietly widen it, and do not quietly drop
   part of it. If part is blocked, finish the rest and say what you left out.
10. **When two readings of a task lead to materially different work, ask.** Otherwise
    make the call, state the assumption, and keep going.

---

## Precedence

When sources disagree, this is the order:

1. **The code**, for what the system currently does.
2. **Your specification**, for what it is supposed to do — however you keep it. A gap
   between spec and code is a bug in one of them; say which.
3. **These guidelines**, for how to work.
4. **[`../../AGENTS.md`](../../AGENTS.md)** and the tooling docs, for the repository.

A rule you disagree with is worth raising. A rule you silently ignore is worth reverting.

---

## Adding to this folder

Keep documents focused and numbered. A new area gets a new file rather than a new
section in an existing one — `06-performance.md`, `07-accessibility.md` — so an agent
can load only what the task needs. Add the row to the table above in the same commit.
