# Testing

How the suites are wired is [`../testing.md`](../testing.md). Where test files go is
[`../../tests/README.md`](../../tests/README.md). **This is about what makes a test worth
keeping.**

The premise: a green suite is only worth something if it would go red when the code
breaks. Most of the rules below exist because a suite in this repository was green
against real bugs.

---

## Test as you build, not afterwards

Every feature ships with its tests **in the same pull request**. Not in a follow-up, not
in a "testing milestone", not once the design settles.

The order, per behaviour:

1. Write a test naming the behaviour you are about to build.
2. Run it. **Watch it fail, and read the failure** — if it fails because of a typo
   rather than the missing behaviour, you are not testing what you think.
3. Write the smallest code that passes.
4. Refactor with the test green.

Two behaviours means two loops. Do not write six tests, then six implementations.

A test written after the code asserts what the code happens to do, including its bugs. A
test written first asserts what the code is *for*.

---

## What the suite covers, and what it cannot

Tests live in `tests/Unit/`, run against a mocked WordPress, and finish in milliseconds.
They cover calculations, date logic, price rounding, state transitions, formatting —
anything with branches.

**If a test has to mock every WordPress function the code calls, it is asserting against
the mocks, not the code.** That is a design signal: extract the logic from the WordPress
plumbing so there is something left to test.

A few things genuinely cannot be asserted without a running site — `dbDelta()` producing
the schema you meant, a query returning what you expect, a capability resolving through
real roles. Check those by hand against `./scripts/dev up`, and write the steps down so
they are repeatable.

---

## What makes a test worth keeping

### 1. Stubs must be as discriminating as the real function

This is the rule that matters most, and the easiest to get wrong.

```php
// Wrong. Returns the active-plugin list for EVERY option key, so a stray
// get_option( 'something_else' ) silently receives it and the test still passes.
Functions\when( 'get_option' )->justReturn( [ 'woocommerce/woocommerce.php' ] );

// Right. Answers the key it was asked about, and returns the caller's default
// for anything else — exactly as WordPress would.
Functions\when( 'get_option' )->alias(
    fn ( string $option, mixed $default_value = false ): mixed =>
        'active_plugins' === $option ? $this->active_plugins : $default_value
);
```

The same applies to any stub that takes a discriminating argument. `current_user_can()`
stubbed to `justReturn( true )` cannot tell `install_plugins` from `activate_plugins`,
so a notice checking the wrong capability passes. That exact bug survived a green suite
here.

`justReturn()` is fine for genuinely argument-independent stubs (`is_multisite()`), and
for `__()`/`esc_html()` identity stubs.

### 2. Never assert on a value the test itself supplied

```php
// Wrong. The stub returns the URL; the assertion checks the URL came back.
// This passes even if the source builds a completely different link.
Functions\when( 'add_query_arg' )->justReturn( 'https://example.test/x?action=install-plugin' );
$this->assertStringContainsString( 'https://example.test/x?action=install-plugin', $output );

// Right. The helper does real work, so the assertion checks what the SOURCE built.
Functions\when( 'add_query_arg' )->alias(
    static fn ( array $args, string $url ): string => $url . '?' . http_build_query( $args )
);
$this->assertStringContainsString( 'action=install-plugin', $output );
$this->assertStringContainsString( 'plugin=woocommerce', $output );
```

If removing the source code under test would leave the assertion passing, the test is
decoration.

### 3. Model state, don't repeat stubs

When three lines of stubbing precede every test, the one thing that varies is buried.
Model the world once on the shared `*_TestCase`, expose it as properties, and let each
test change only what it is exercising:

```php
$this->install_woocommerce( '10.0' );        // installed, active, outdated
$this->capabilities = [ 'install_plugins' ];
```

A reader can now see the scenario. See `tests/Unit/Requirements_TestCase.php`.

### 4. Cover boundaries, not just happy and sad

A `>=` comparison has three interesting inputs: below, exactly at, above. Test all
three. The off-by-one is the bug that ships.

Use a data provider so the table is the test:

```php
/**
 * @dataProvider wordpress_versions
 */
public function test_wordpress_version_is_checked_against_the_minimum(
    string $installed,
    bool $expected_failure
): void {
    $this->wp_version = $installed;

    $failures = $this->requirements()->require_wp( '6.5' )->failures();

    $this->assertSame( $expected_failure, isset( $failures[0] ) );
}

/**
 * @return array<string, array{string, bool}>
 */
public static function wordpress_versions(): array {
    return [
        'one minor below the minimum' => [ '6.4', true ],
        'exactly the minimum'         => [ '6.5', false ],
        'one minor above'             => [ '6.6', false ],
    ];
}
```

String keys name the case, so a failure says which row broke.

### 5. Test the properties, not only the return value

If the code caches, prove it is called once. If it is idempotent, run it twice. If it
must not fire on a single site, assert it does not. These are the behaviours that break
silently in production, because nothing about the return value reveals them.

### 6. One behaviour per test, named after the behaviour

`test_fails_when_wordpress_version_is_below_minimum`, not `test_failures`. When it goes
red in CI, the name alone should tell you what broke.

### 7. Assert exactly

`assertSame()` over `assertEquals()`. Assert the whole array for a structural contract
rather than poking one key — a test that checks `status` alone will not notice when
`min_version` stops being reported.

### 8. Deterministic, isolated, order-independent

No network, no clock, no shared filesystem, no leaking globals. `setUp()` resets
everything. If test A must run before test B, one of them is wrong.

Constants are the exception you cannot design away: PHP cannot undefine one, so anything
the plugin reads from a constant is defined once in `tests/bootstrap/unit.php` and never
by a test. A test that `define()`s its own has changed the world for every test after it.

---

## The mutation check

**Before you commit a test, break the code and watch it fail.**

Invert a condition, change `>=` to `>`, delete the guard clause, return early. If the
suite stays green, the test does not test what you believe it tests. Put the source
back.

This takes thirty seconds and is the only direct evidence that a test has value.
Coverage percentage is not that evidence: a test that executes a line without asserting
on its effect covers it and proves nothing.

For a change that matters, record the result in the PR:

| Mutation | Result |
| --- | --- |
| `install_plugins` → `activate_plugins` in the notice | 🔴 2 failures |
| `>=` → `>` in the version comparison | 🔴 7 failures |
| Memoisation removed | 🔴 1 failure |

---

## What not to test

Effort spent here is effort not spent on the branchy code that actually breaks.

- **WordPress or WooCommerce itself.** Assume `get_option()` works.
- **Language behaviour.** Constructors assign; PHP handles that.
- **Getters with no logic.**
- **Controllers.** They pull request data, call a service, and render a template or send
  a response — view/glue code with no branchy logic of its own to break. The service
  underneath it gets the test.
- **A class with no logic of its own beyond fulfilling an interface.** A `ServiceProvider`
  that only registers classes, a value object that only holds and returns constructor
  arguments — there is no branch to break, so there is nothing a test would catch.
- **Private methods directly.** Test them through the public API. If a private method is
  unreachable that way, the class needs a better seam, not a reflection hack.
- **Escaping, via identity-stubbed `esc_*` functions.** You would be asserting on the
  stub. PHPCS's `WordPress.Security` sniffs check escaping with real context.
- **Exact HTML structure**, beyond what the behaviour requires. Assert the message and
  the link target; do not pin every `<div>`, or every markup tweak becomes a test change.

---

## Definition of tested enough

For each behaviour you added:

- [ ] The success path.
- [ ] Every failure mode the code distinguishes — if it reports three statuses, three
      tests.
- [ ] Every boundary of every comparison.
- [ ] The empty and missing cases: no rows, no version header, nothing configured.
- [ ] Caching, idempotency, and "must not happen" behaviours, asserted directly.
- [ ] At least one mutation, tried and caught.

Anything touching money, dates or data a user can lose gets the strictest reading of
this list. Those are the defects that cannot be fixed by shipping a patch.
