# The development loop

One task, start to finish. The order matters: each step protects the ones after it.

```
Understand  →  Branch  →  Test  →  Build  →  Verify  →  Document  →  Commit  →  PR
    │                      ↑         │
    │                      └─────────┘
    │                    red → green, small steps
    └─ if two readings differ materially, ask before branching
```

---

## 1. Understand before you branch

| Question | Where the answer is |
| --- | --- |
| What is this feature meant to do? | Your specification, issue or ticket |
| How does the repository fit together? | [`../architecture.md`](../architecture.md) |
| How do I run anything? | [`../../scripts/README.md`](../../scripts/README.md) |

Read the specification first, not the code. The code tells you what exists; the spec
tells you what is supposed to exist, and the difference is often the task.

**Do not start if the task is ambiguous in a way that changes the work.** One question
now costs less than a day of the wrong thing. See
[when to ask](#when-to-ask-and-when-to-decide).

## 2. Branch

```sh
git checkout main && git pull --ff-only
git checkout -b feat/m05-recurring-engine
```

Naming and the rest of the rules: [02-version-control.md](02-version-control.md).

## 3. Write the test first

Write a test that describes the behaviour you are about to build, and **watch it fail
for the right reason**. A test that passes before you write the code is testing nothing.

This is the cheapest way to find out your seam is wrong. A test that is painful to write
means a class that is painful to use, and you learn it before the implementation exists
rather than after.

Which suite, and what makes the test worth keeping: [03-testing.md](03-testing.md).

## 4. Build the smallest thing that passes

Then improve it with the test green. Resist building the general case before the
specific one exists — see
[the over-engineering test](04-code-quality.md#the-over-engineering-test).

## 5. Verify locally

Run what CI runs, before CI runs it:

```sh
./scripts/dev phpcbf        # auto-fix style
./scripts/dev phpcs         # coding standards
./scripts/dev phpstan       # static analysis
./scripts/dev test          # the unit suite; run constantly
```

The Husky hooks run a subset of these. They are a convenience, not the gate —
[05-ci-cd.md](05-ci-cd.md) is.

## 6. Document in the same change

If behaviour changed, a document describes it somewhere. Find it and update it **in this
PR**, not the next one. The mapping is in
[`../README.md`](../README.md#keeping-these-honest).

## 7. Commit, then open the PR

Small, focused commits with a body explaining *why*. Then a PR that a reviewer can
understand without reading every line of the diff.
Both: [02-version-control.md](02-version-control.md).

---

## Definition of Done

A change is done when **all** of these are true. Not most.

- [ ] The behaviour the task asked for works, and you have seen it work — not inferred
      that it must.
- [ ] Tests cover the new behaviour, including the failure paths and boundaries, and
      they fail when the code is broken.
- [ ] `phpcs`, `phpstan` and the test suites pass locally.
- [ ] Documentation describing the changed behaviour is updated in the same PR.
- [ ] The commit history is clean: conventional subjects, no fixup noise, no
      generated artifacts.
- [ ] Anything you deliberately left out is stated explicitly in the PR body.
- [ ] If the spec was wrong or ambiguous, that is written down — a spec correction, or
      a note in the PR.

**"It passes CI" is not the definition of done.** CI checks what it was told to check.

---

## When to ask, and when to decide

Decide yourself, state the assumption, and continue:

- Naming, file placement, private helper structure.
- Anything where one option is conventional and the others are merely possible.
- Anything the spec already answers, even indirectly.

Stop and ask:

- Two readings of the task lead to materially different work.
- The change would alter data, money, or something users can see, in a way the spec
  does not describe.
- The right fix means changing a rule in the specification.
- You are about to do something hard to reverse: rewriting shared history, deleting a
  branch someone else may be using, force-pushing, changing a release.

**Report honestly.** If tests fail, say so and show the output. If you skipped a step,
say which. Do not describe work as complete when part of it is not — a known gap is a
manageable problem, a hidden one is not.

---

## Working on an existing failure

When something is broken, resist patching the symptom.

1. **Reproduce it in a test first.** The failing test is the bug report that cannot rot.
2. Find the cause, not the place the error surfaced.
3. Fix the cause. Keep the test.
4. Ask whether the same class of bug exists elsewhere, and whether a guideline or a gate
   would have caught it. If one would, add it — that is how this folder grew.
