# CI, gates and releasing

What the workflows *do* is [`../workflows.md`](../workflows.md). This is what to do about
them: which gate protects what, how to respond when one fails, and how a change reaches
a release.

---

## The gates

| Gate | Runs | Protects against |
| --- | --- | --- |
| **Husky pre-commit** | `phpcbf` on staged PHP | Style noise in the diff |
| **Husky pre-push** | `phpcs`, `phpstan` | Pushing a branch CI will obviously reject |
| **commitlint** | Every commit on a PR | History that cannot be read or released from |
| **lint.yml** | PHPCS, PHPStan, editorconfig, PHP 5.6 parse | Style drift, type errors, whitespace, a pre-flight file that cannot parse on old PHP |
| **test.yml** | The unit suite across PHP 8.0–8.5 | Behaviour regressions, version-specific breakage |
| **release.yml** | Tag push | Shipping a mismatched version or an unscoped build |

**The Husky hooks are a convenience, not the gate.** They run locally, they can be
skipped, and they check a subset. CI is the gate. Never use `--no-verify` to get past a
hook — if a hook is wrong, fix the hook.

## Run the gate before it runs you

```sh
./scripts/dev phpcbf && ./scripts/dev phpcs && ./scripts/dev phpstan && ./scripts/dev test
```

Everything except the version matrix, in about a minute. There is no reason to discover
a PHPCS violation from a CI email.

---

## When a gate fails

**Fix the cause. Do not weaken the gate.**

The failure is information. A PHPStan error usually means the types really are wrong; a
failing test on PHP 8.0 usually means you used syntax the plugin claims to support and
does not.

In order of preference:

1. **Fix the code.** Almost always the right answer.
2. **Fix the test**, if the test encoded the old behaviour and the new behaviour is
   correct and intended. Say so in the commit — a changed assertion is a claim that the
   contract changed.
3. **Narrow the exception**, if the tool is genuinely wrong. A scoped
   `phpstan.neon.dist` `ignoreErrors` entry with an `identifier` and a `paths` list, or
   a targeted PHPCS `exclude-pattern` — never a blanket suppression, and always with a
   comment saying why the tool is wrong.

### What is never acceptable

- `--no-verify`, or deleting an assertion to get green.
- Marking a test skipped or incomplete to move on, without an issue and a comment.
- Raising a memory or time limit to hide unbounded work.
- Committing with a failing gate and "will fix in the next PR".

### Disabling a job

Sometimes a suite genuinely must be parked — an environment problem, a dependency
mid-migration. If so, it is a decision that gets written down in three places:

1. **In the workflow**, as a comment saying it is temporary, why, and how to re-enable.
2. **In the commit message**, as a `ci(...)` commit that explains itself.
3. **In [`../workflows.md`](../workflows.md)**, so nobody reads the docs and believes a
   job is running when it is not.

A parked job is a state to get out of, not to normalise.

---

## Dependencies

- Renovate opens the updates; a human decides. Read the changelog for anything that is
  not a patch.
- **Raising `phpunit/phpunit` past `^9.6` means dropping PHP 8.0** —
  [why](../testing.md#the-phpunit-96-ceiling). Treat a Renovate PR that does it as a
  proposal to drop a supported version, not a version bump.
- **A change to the PHP floor is four changes**, and the tools contradict each other
  until all four land:
  [the list](../standards.md#the-php-floor-is-declared-in-four-places).
- A new runtime dependency goes in `plugin/composer.json`, is added with
  `./scripts/dev plugin require`, and is imported through the scoped prefix —
  [why](../architecture.md#adding-a-runtime-dependency).

---

## Releasing

Tag-driven; the workflow does the rest. See [`../workflows.md`](../workflows.md) for the
pipeline.

```sh
# 1. Bump BOTH the `Version:` header and the *_VERSION constant. They must match
#    the tag's base version or release.yml fails the build.
# 2. Merge that through a PR like any other change.
# 3. Tag the merge commit on main.
git tag v1.2.3
git push origin v1.2.3
```

A hyphenated tag (`v1.2.3-rc.1`) publishes as a pre-release, and the plugin file stays
at the stable version — only the tag differs.

### Before tagging

- [ ] `main` is green.
- [ ] Both version locations match the tag's base version.
- [ ] The changes since the last tag are ones you meant to ship.
- [ ] Any database migration has been run forwards on a copy of real data.
- [ ] Anything that needs a live WordPress has been checked by hand against
      `./scripts/dev up`.

**Cutting a release is the repository owner's call.** An agent prepares the version
bump and opens the PR; it does not push the tag unless asked.
