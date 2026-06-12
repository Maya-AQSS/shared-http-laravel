<?php

declare(strict_types=1);

use Maya\Search\AccentFold;

// ─── fold() ──────────────────────────────────────────────────────────────────

it('folds lowercase accented vowels', function (): void {
    expect(AccentFold::fold('áéíóú'))->toBe('aeiou');
});

it('folds uppercase accented vowels via mb_strtolower', function (): void {
    expect(AccentFold::fold('ÁÉÍÓÚ'))->toBe('aeiou');
});

it('folds ñ to n', function (): void {
    expect(AccentFold::fold('ñ'))->toBe('n');
    expect(AccentFold::fold('Ñ'))->toBe('n');
});

it('folds ü to u', function (): void {
    expect(AccentFold::fold('ü'))->toBe('u');
    expect(AccentFold::fold('Ü'))->toBe('u');
});

it('folds ß to ss', function (): void {
    expect(AccentFold::fold('straße'))->toBe('strasse');
});

it('folds capital ẞ to ss', function (): void {
    expect(AccentFold::fold('STRASSE'))->toBe('strasse');
    expect(AccentFold::fold('STRAẞE'))->toBe('strasse');
});

it('folds œ to oe and æ to ae', function (): void {
    expect(AccentFold::fold('œuvre'))->toBe('oeuvre');
    expect(AccentFold::fold('Æther'))->toBe('aether');
});

it('trims surrounding whitespace', function (): void {
    expect(AccentFold::fold('  café  '))->toBe('cafe');
});

it('returns empty string unchanged', function (): void {
    expect(AccentFold::fold(''))->toBe('');
});

it('leaves ascii unchanged', function (): void {
    expect(AccentFold::fold('hello world'))->toBe('hello world');
});

it('folds a mixed real-world string', function (): void {
    // "Niño García López" → "nino garcia lopez"
    expect(AccentFold::fold('Niño García López'))->toBe('nino garcia lopez');
});

// ─── escapeLike() ─────────────────────────────────────────────────────────────

it('escapes percent wildcard', function (): void {
    expect(AccentFold::escapeLike('100%'))->toBe('100\%');
});

it('escapes underscore wildcard', function (): void {
    expect(AccentFold::escapeLike('a_b'))->toBe('a\_b');
});

it('escapes backslash', function (): void {
    expect(AccentFold::escapeLike('a\\b'))->toBe('a\\\\b');
});

it('escapes all wildcards together', function (): void {
    expect(AccentFold::escapeLike('%_\\'))->toBe('\%\_\\\\');
});

it('returns empty string unchanged for escapeLike', function (): void {
    expect(AccentFold::escapeLike(''))->toBe('');
});

it('does not escape safe characters', function (): void {
    expect(AccentFold::escapeLike('hello world'))->toBe('hello world');
});

// ─── sqlFoldedLowerColumn() ───────────────────────────────────────────────────

it('returns translate expression for pgsql driver', function (): void {
    [$expr, $bindings] = AccentFold::sqlFoldedLowerColumn('name', 'pgsql');

    expect($expr)->toContain('translate(lower(name)');
    expect($expr)->toContain('replace(');
    expect($bindings)->toHaveCount(2);
    expect($bindings[0])->toBeString()->not->toBeEmpty(); // from chars
    expect($bindings[1])->toBeString()->not->toBeEmpty(); // to chars
});

it('pgsql translate bindings have equal lengths', function (): void {
    [$expr, $bindings] = AccentFold::sqlFoldedLowerColumn('title', 'pgsql');

    expect(mb_strlen($bindings[0], 'UTF-8'))->toBe(mb_strlen($bindings[1], 'UTF-8'));
});

it('returns lower expression only for sqlite driver', function (): void {
    [$expr, $bindings] = AccentFold::sqlFoldedLowerColumn('name', 'sqlite');

    expect($expr)->toBe('lower(name)');
    expect($bindings)->toBeEmpty();
});

it('pgsql expression contains oe and ae replacements', function (): void {
    [$expr] = AccentFold::sqlFoldedLowerColumn('col', 'pgsql');

    expect($expr)->toContain("'oe'");
    expect($expr)->toContain("'ae'");
});

it('pgsql expression contains ss replacement for ß', function (): void {
    [$expr] = AccentFold::sqlFoldedLowerColumn('col', 'pgsql');

    expect($expr)->toContain("'ss'");
});

it('wraps arbitrary SQL expressions via sqlFoldedLowerExpression for pgsql', function (): void {
    [$expr] = AccentFold::sqlFoldedLowerExpression('COALESCE(first_name, last_name)', 'pgsql');

    expect($expr)->toContain('lower(COALESCE(first_name, last_name))');
});

it('rejects non-identifier input in sqlFoldedLowerColumn (SQL injection guard)', function (): void {
    expect(fn () => AccentFold::sqlFoldedLowerColumn('name); DROP TABLE users;--', 'pgsql'))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => AccentFold::sqlFoldedLowerColumn('COALESCE(a, b)', 'pgsql'))
        ->toThrow(\InvalidArgumentException::class);
});

it('accepts schema-qualified identifiers in sqlFoldedLowerColumn', function (): void {
    [$expr] = AccentFold::sqlFoldedLowerColumn('users.name', 'pgsql');

    expect($expr)->toContain('lower(users.name)');
});

it('throws for unknown driver', function (): void {
    expect(fn () => AccentFold::sqlFoldedLowerColumn('col', 'sqlsrv'))
        ->toThrow(\InvalidArgumentException::class);
});
