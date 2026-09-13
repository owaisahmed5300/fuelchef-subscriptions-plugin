# Comments and PHPDoc

A comment is for the reader of the code today, not a log of how it got here.

## What a comment is for

- Say something the code itself can't: a non-obvious constraint ("must still parse on
  PHP 5.3"), a gotcha a reader would trip over ("MySQL treats every `NULL` as distinct in
  a `UNIQUE` index"), or what a block does when that isn't obvious at a glance.
- One line. If it needs a paragraph, the code is probably the thing to fix.

## What to avoid

- **Why it used to be different, or why you changed it.** That's what the commit message
  is for. A comment describes the code as it stands, not its history.
- **Restating the code in prose.** If a comment just repeats the function or variable
  name, delete it — see [`04-code-quality.md`](04-code-quality.md#write-for-the-reader):
  a comment explaining a name means the name is wrong.
- **A docblock on a method that already says everything.** A one-line getter, a thin
  factory, an obvious override — needs no `/** ... */` beyond what PHPCS actually
  requires (a `@param`/`@return` where the type isn't otherwise declared).
- **Padding.** "Note that", "it's important to", "as mentioned above" — cut them; the
  sentence reads the same without them.

## What good looks like

Terse, present tense, describes current behaviour only:

```php
// dbDelta() wants a literal string or array of them; a dynamic method call is `mixed`.
/** @var array<string>|string $schema */
$schema = $this->$method();
```

Not:

```php
// We used to call dbDelta() directly here, but PHPStan level 10 flagged it because
// dynamic method calls return mixed, so after some investigation we changed it to
// assign to a variable first with an explicit @var annotation before the call.
```

The second one explains a decision no one asked about; the first says what's true right
now. If you want to explain a decision, that belongs in the commit that made it.
