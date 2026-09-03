# tests/

The plugin's unit test suite. No WordPress is loaded; WordPress functions and hooks are
mocked with [Brain Monkey](https://github.com/Brain-WP/BrainMonkey).

```
tests/
├── bootstrap/
│   ├── unit.php           # PHPUnit bootstrap (autoloader + plugin constants)
│   └── phpstan.php        # PHPStan bootstrap (constants only)
├── TestCase.php           # Base class: brings Brain Monkey up and down
└── Unit/                  # One file per source class (see the rule below)
```

```sh
./scripts/dev test
```

## Where a test file goes

A test file mirrors the namespace and directory of the class it covers under
`plugin/src/`:

- `plugin/src/Database/Installer.php` → `tests/Unit/Database/Installer_Test.php`,
  namespace `FuelChef\Subscriptions\Tests\Unit\Database`
- `plugin/src/Plugin.php` → `tests/Unit/Plugin_Test.php`, namespace
  `FuelChef\Subscriptions\Tests\Unit`

The class under test gets a test class named `X_Test`, and the file must end in
`Test.php` or PHPUnit will not collect it. Shared scaffolding must therefore *not* end
in `Test.php` — hence `Requirements_TestCase.php`.

## Further reading

- [Testing docs](../docs/testing.md) — how the suite is wired, conventions, examples.
- [Testing guidelines](../docs/guidelines/03-testing.md) — what makes a test worth
  keeping.
- [Scripts README](../scripts/README.md) — the commands.
