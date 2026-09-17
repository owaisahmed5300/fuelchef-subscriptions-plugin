# Comments and PHPDoc

A comment is for the reader of the code today, not a log of how it got here.

This applies whether the code was written by a person or an AI agent. An agent's source
comments document what the code does, never the agent's own task, reasoning, alternatives
considered, or what changed and why — that belongs in the commit message, the PR
description, or the chat response, never in the file itself.

## What a comment is for

- Say something the code itself can't: a non-obvious constraint ("must still parse on
  PHP 5.3"), a gotcha a reader would trip over ("MySQL treats every `NULL` as distinct in
  a `UNIQUE` index"), or what a block does when that isn't obvious at a glance.
- One line. If it needs a paragraph, the code is probably the thing to fix.

## What to avoid

- **Why it used to be different, or why you changed it.** That's what the commit message
  is for. A comment describes the code as it stands, not its history. Don't note what the
  code used to do, that an approach is "better" or "cleaner", or why you picked it over an
  alternative — a reader sees only the current code, never the ones you didn't write.
- **Restating the code in prose.** If a comment just repeats the function or variable
  name, delete it — see [`04-code-quality.md`](04-code-quality.md#write-for-the-reader):
  a comment explaining a name means the name is wrong.
- **Padding.** "Note that", "it's important to", "as mentioned above" — cut them; the
  sentence reads the same without them.
- **Em dashes, and stiff or overly formal phrasing.** Write the way you'd explain it to a
  colleague: short sentences, plain words. A comma or a period does what an em dash was
  doing.

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

---

## PHPDocs

A PHPDoc block has two jobs: tell the reader in one line what the method does, and give
PHPCS/PHPStan the types they can't infer from the signature. Skipping the first job to
satisfy only the second is the usual way this goes wrong.

### Every method's docblock has a description

One line saying what it does, in plain words — `Builds a schedule from a database row.`,
not `Handles the schedule building process.` Add a short second paragraph only when the
one line needs a caveat the signature doesn't carry (a non-obvious precondition, what
happens on an edge case).

This applies to **every** method that has a docblock at all: private and protected
methods, and overrides of an abstract or interface method — not just the public API.
Repeating (in your own words) what the parent already documented is fine; a bare
`@param`/`@return` block with no description line is not.

```php
/**
 * Builds the row data to write for a schedule.
 *
 * @return array<string, mixed> The row data to persist.
 */
protected function dehydrate( Entity $entity ): array {
```

Not:

```php
/**
 * @return array<string, mixed>
 */
protected function dehydrate( Entity $entity ): array {
```

A one-line getter, a thin factory, or an obvious override that needs no docblock at all
(nothing PHPCS requires and nothing worth a reader's second look) can stay undocumented.
The rule is about docblocks that exist, not about forcing one onto every method.

### Every `@param`, `@return` and `@throws` carries an explanation, and ends with a dot

The type alone tells a reader nothing about what the value *means*. Say what it is, and
end the sentence with a period — including on a bare collection or template type:

```php
/**
 * @param array<string, mixed> $row Raw database row.
 *
 * @return TEntity The hydrated entity.
 *
 * @throws Repository_Exception When the insert fails.
 */
```

Not:

```php
/**
 * @param array<string, mixed> $row
 *
 * @return TEntity
 */
```

### Don't document `InvalidArgumentException` with `@throws`

It marks a caller's own programming error (a bad argument), not a condition the caller is
expected to catch or a business rule worth a controller-facing message — unlike
`Entity_Not_Found_Exception` or `Repository_Exception`, which are. Keep the `throw`
itself; leave it off the docblock.

### Plain, simple wording

Short sentences, ordinary words, no em dashes. If a sentence needs a semicolon or three
clauses to say what it means, it is two sentences. The same rule that applies to comments
above applies here: describe what the code does, not why you wrote it that way or how it
compares to some other approach — save that for the commit message, and mention it in the
chat response only if it's something worth the reviewer's attention.
