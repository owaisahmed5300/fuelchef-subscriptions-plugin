# Version control

Git history is documentation that cannot go stale. `git log` should read as the story of
how the plugin came to be, and `git blame` should land on a commit that explains itself.

Enforced by commitlint and the Husky hooks; the rest is discipline.

---

## Branches

**Never commit to `main`.** Ever, for any reason, however small the change.

```
feat/…    a new capability          feat/m05-recurring-engine
fix/…     a defect in behaviour     fix/duplicate-order-on-retry
refactor/…  behaviour unchanged     refactor/extract-delivery-repository
test/…    tests only                test/requirements-suite-hardening
docs/…    documentation only        docs/agent-guidelines
chore/…   tooling, deps, config     chore/bump-phpstan
ci/…      workflows                 ci/cache-composer
```

One concern per branch. A branch that fixes a bug *and* renames a class is two branches,
and the reviewer of each will do a better job.

For milestone work, one branch per milestone: `feat/m03-classic-checkout`.

---

## Commits

### Conventional Commits, enforced

```
<type>(<scope>): <subject>

<body — why, not what>

<footer>
```

Types: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `style`, `build`, `ci`,
`chore`, `revert`. Scope is the area touched (`requirements`, `deliveries`, `checkout`,
`container`) and is optional but usually worth it.

Subject line: imperative mood, lower case, no trailing period, under ~72 characters.
"add cutoff calculator", not "Added cutoff calculator." or "adds".

### The body explains why

The diff already shows what changed. The body exists for what the diff cannot show:
the reason, the alternative you rejected, the constraint that forced the shape.

```
fix(deliveries): lock the delivery row before creating its order

Two cron workers overlapping could both pass the "not yet locked" check and
create two orders for the same delivery, charging the customer twice. The
window is small but we saw it in staging under a retry storm.

Takes a row lock inside the transaction rather than relying on the status
check, so the second worker blocks and then sees the locked row.
```

If a commit needs no body, it probably needed no explanation — that is fine and common
for `chore` and `style`. It is rarely true for `fix`.

### One concern per commit

A commit should be revertible on its own. If reverting it would take out an unrelated
change, split it.

Practical test: can you write the subject line without "and"? If not, use `git add -p`
and split.

### What never goes in a commit

- `vendor/`, `plugin/vendor/`, `plugin/vendor-prefixed/`, `plugin/composer.lock`,
  `node_modules/`, build zips, `.phpcs.cache`, `.phpstan.cache`, `.phpunit.result.cache`.
- Commented-out code. Git remembers it; you do not need to.
- Debug output, `var_dump`, `error_log` left behind, or a `TODO` without an owner and a
  reason.
- Unrelated reformatting mixed into a behavioural change. Reformat in its own `style`
  commit or the review becomes worthless.
- Secrets, credentials, or a real customer's data — including in test fixtures.

---

## Pull requests

**The PR body is written for the reviewer, not for the author.** Assume they have not
read the diff and do not remember the task.

Cover, briefly:

- **What changed and why.** Lead with the problem, not the solution.
- **Anything surprising** — a decision that looks wrong without context, a workaround, a
  deliberate omission.
- **How it was verified.** Which commands, which results. "Tests pass" is weaker than
  "36 tests, 54 assertions; PHPStan clean".
- **What you did not do**, if the task implied more than you delivered.

Keep PRs small enough to review properly. If a PR cannot be reviewed in one sitting,
stack it: open the second PR with the first as its base, and say so at the top. GitHub
retargets it to `main` automatically when the base merges.

Do not merge your own PR without review unless the repository owner has said to. Do not
merge with a failing gate.

---

## Rewriting history

Rebasing to tidy *your own unmerged branch* is encouraged. Rewriting anything else is a
decision, not a convenience.

### Safe to rewrite

- Your branch, not yet pushed.
- Your branch, pushed, on a PR nobody has reviewed or built on.

### Not safe without asking

- `main`, or any branch someone else has based work on.
- A branch with review comments anchored to commits — rewriting orphans them.

### The recipe

Interactive rebase is unavailable in some agent environments, and is easy to get wrong
regardless. This does the same job deterministically:

```sh
# 1. Never rewrite with uncommitted work in the tree.
git stash push -m "wip"

# 2. A backup branch costs nothing and has saved this repository before.
git branch backup/pre-tidy

# 3. Rebuild from the last commit you want to keep as-is.
git reset --hard <parent-of-first-commit-to-change>
git cherry-pick <commit>
git cherry-pick --no-commit <fixup-commit>   # fold a fixup into the one before it
git commit --amend --no-edit
git cherry-pick <remaining-commit>

# 4. PROVE you changed only the history, not the content.
git diff backup/pre-tidy HEAD --stat          # must be empty

# 5. Restore your work, then push.
git stash pop
git push --force-with-lease origin <branch>
git branch -D backup/pre-tidy                 # only once the push is verified
```

**Step 4 is not optional.** An empty diff is the difference between "I tidied the
history" and "I hope I did not lose anything".

**Always `--force-with-lease`, never `--force`.** Plain `--force` overwrites whatever is
on the remote, including a commit someone else pushed while you worked.

### When it goes wrong

`git reflog` has every commit HEAD has pointed at, including ones no branch references.
Nothing committed is lost until the reflog expires.

```sh
git reflog
git reset --hard HEAD@{3}
```

Do not `reset --hard` while the tree is dirty — stash first, always.

---

## Rules specific to AI agents

Every rule above applies. These fail more often when an agent is at the keyboard:

- **Check the tree before any destructive command.** `git status` first. `reset --hard`,
  `checkout -f` and `clean -fd` discard uncommitted work silently and permanently.
- **Read a file before overwriting it.** Especially one you did not write in this
  session.
- **Do not merge to `main` or push a tag unless explicitly asked.** Opening a PR is
  routine; merging it is the owner's decision. So is cutting a release.
- **Do not delete branches you did not create**, and say so when you delete one you did.
- **Say when you rewrote history.** "Force-pushed after folding a fixup commit; verified
  the tree is byte-identical" is the sentence. Silence here is how trust is lost.
- **Do not fabricate verification.** If you did not run a check, say you did not run it.
  A reported pass that never happened is worse than an admitted gap.
- **Do not weaken a gate to get green** — see [05-ci-cd.md](05-ci-cd.md).
