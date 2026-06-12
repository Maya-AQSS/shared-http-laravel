<?php

declare(strict_types=1);

namespace Maya\Search;

/**
 * Accent-folding and LIKE-escape utilities for cross-driver search.
 *
 * ## Provenance
 * Unified from two diverging implementations:
 *   - `maya_dms/backend/app/Support/SearchAccentFold` — `fold()`, `escapeLike()`,
 *     and `sqlFoldedLowerColumn()` using `translate(lower(...), ?, ?)` for pgsql.
 *   - `maya_logs/backend/app/Support/LikeEscaper` — `escapeLikePattern()` using
 *     `!` as the escape character instead of backslash.
 *
 * ## Design decisions
 *
 * ### Escape character: backslash (dms) vs `!` (logs)
 * DMS uses `addcslashes($v, '%_\\')` → backslash escape, which is the SQL standard
 * and works in both PostgreSQL and SQLite without requiring ESCAPE clause.
 * Logs uses `!` as escape character and requires `LIKE '%foo%' ESCAPE '!'` in queries.
 * **Decision: adopt backslash** (dms convention). It is database-agnostic, avoids
 * the ESCAPE clause, and is consistent with Laravel's own `Builder::escapeLikeValue()`.
 *
 * ### sqlFoldedLowerColumn() driver parameter
 * DMS only targets PostgreSQL and always returns a `translate(lower(...))` expression.
 * Tests and Testbench run on SQLite, which does not have `translate()`.
 * **Decision: accept `$driver` parameter** (`'pgsql'` or `'sqlite'`).
 * - `pgsql`: full accent-fold via `translate(lower(col), from, to)` + replace chain.
 * - `sqlite`: `lower(col)` only (SQLite's ICU extension is rarely available; callers
 *   should fold in PHP before building LIKE patterns for SQLite).
 * - Any other driver throws `\InvalidArgumentException` to surface misconfiguration.
 *
 * ### Character map (pgsql translate from/to)
 * Pairs are 1:1 (single UTF-8 code-point → single ASCII code-point) so they are safe
 * to pass as PostgreSQL `translate(str, from, to)` arguments. Multi-codepoint ligatures
 * (œ→oe, æ→ae, ß→ss, ẞ→ss) are handled separately by a `replace()` chain because
 * `translate()` maps each character individually and cannot expand 1→2.
 */
final class AccentFold
{
    /**
     * Map of lowercase UTF-8 characters (single code-point) → ASCII base.
     * Used for both PHP-side folding and the PostgreSQL translate() pair.
     *
     * @return array<string,string>
     */
    private static function charMap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $pairs = [
            ['á', 'a'], ['à', 'a'], ['â', 'a'], ['ã', 'a'], ['ä', 'a'], ['å', 'a'], ['ā', 'a'], ['ă', 'a'],
            ['ą', 'a'], ['ǎ', 'a'], ['ǟ', 'a'], ['ǡ', 'a'], ['ǻ', 'a'],
            ['é', 'e'], ['è', 'e'], ['ê', 'e'], ['ë', 'e'], ['ē', 'e'], ['ė', 'e'], ['ę', 'e'], ['ě', 'e'],
            ['í', 'i'], ['ì', 'i'], ['î', 'i'], ['ï', 'i'], ['ī', 'i'], ['į', 'i'], ['ǐ', 'i'],
            ['ó', 'o'], ['ò', 'o'], ['ô', 'o'], ['õ', 'o'], ['ö', 'o'], ['ō', 'o'], ['ő', 'o'], ['ǒ', 'o'],
            ['ǫ', 'o'], ['ǭ', 'o'],
            ['ú', 'u'], ['ù', 'u'], ['û', 'u'], ['ü', 'u'], ['ū', 'u'], ['ů', 'u'], ['ű', 'u'], ['ǔ', 'u'],
            ['ǖ', 'u'], ['ǘ', 'u'], ['ǚ', 'u'], ['ǜ', 'u'],
            ['ñ', 'n'], ['ń', 'n'], ['ň', 'n'], ['ņ', 'n'],
            ['ç', 'c'], ['ć', 'c'], ['č', 'c'], ['ĉ', 'c'], ['ċ', 'c'],
            ['ý', 'y'], ['ÿ', 'y'], ['ỳ', 'y'], ['ŷ', 'y'], ['ȳ', 'y'],
            ['ł', 'l'], ['ľ', 'l'], ['ļ', 'l'], ['ŀ', 'l'],
            ['đ', 'd'], ['ď', 'd'],
            ['ř', 'r'], ['ŕ', 'r'], ['ŗ', 'r'],
            ['ś', 's'], ['š', 's'], ['ş', 's'], ['ș', 's'],
            ['ź', 'z'], ['ž', 'z'], ['ż', 'z'],
            ['ğ', 'g'], ['ǧ', 'g'], ['ģ', 'g'],
            ['ķ', 'k'],
        ];

        $map = [];
        foreach ($pairs as [$from, $to]) {
            if (mb_strlen($from, 'UTF-8') !== 1 || mb_strlen($to, 'UTF-8') !== 1) {
                continue;
            }
            if (!isset($map[$from])) {
                $map[$from] = $to;
            }
        }

        return $map;
    }

    /**
     * Fold accents in a string: lowercases, trims, expands ligatures (œ→oe, æ→ae)
     * and ß/ẞ→ss, then maps remaining accented characters to their ASCII base.
     *
     * Semantically identical to `maya_dms/SearchAccentFold::fold()`.
     */
    public static function fold(string $value): string
    {
        $s = mb_strtolower(trim($value), 'UTF-8');
        $s = str_replace(['ß', 'ẞ'], 'ss', $s);
        $s = strtr($s, ['œ' => 'oe', 'æ' => 'ae']);
        $s = strtr($s, self::charMap());

        return $s;
    }

    /**
     * Escape SQL LIKE special characters using backslash.
     *
     * Escapes `%`, `_`, and `\` so they are treated as literals.
     * Backslash is the default LIKE escape character in PostgreSQL and SQLite
     * and does not require an explicit `ESCAPE` clause.
     *
     * **Divergence from maya_logs/LikeEscaper**: that class uses `!` as the escape
     * character and requires `LIKE pattern ESCAPE '!'` on every query. This method
     * adopts the DMS convention (backslash) for database-agnostic usage.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Build a driver-aware SQL expression that lower-cases and accent-folds a column.
     *
     * @param  string $column  Column identifier (optionally schema/table-qualified).
     *                         For arbitrary SQL expressions use {@see sqlFoldedLowerExpression()}.
     * @param  string $driver  Database driver: `'pgsql'` or `'sqlite'`.
     *
     * @return array{0: string, 1: list<string>}
     *   - `[0]` SQL expression with `?` placeholders
     *   - `[1]` Positional bindings (empty for sqlite)
     *
     * @throws \InvalidArgumentException for unsupported drivers.
     *
     * ### pgsql
     * Returns `replace(replace(replace(replace(translate(lower(col),?,?),'œ','oe'),'æ','ae'),'ß','ss'),'ẞ','ss')`
     * with two bindings: `$from` chars (UTF-8) and `$to` chars (ASCII), same length.
     *
     * ### sqlite
     * Returns `lower(col)` with no bindings. SQLite's `lower()` is ASCII-only but
     * the PHP-side `fold()` should be applied to the query value before wrapping in
     * `escapeLike()`, so the comparison is fold(value) LIKE lower(col) pattern.
     */
    public static function sqlFoldedLowerColumn(string $column, string $driver): array
    {
        self::assertSafeColumnIdentifier($column);

        return self::sqlFoldedLowerExpression($column, $driver);
    }

    /**
     * Variante para expresiones SQL arbitrarias (p.ej. `snapshot_data->'t'->>'name'`,
     * `COALESCE(a, b)`). La expresión se interpola SIN validar: SOLO expresiones
     * construidas por el desarrollador — NUNCA derivadas de input de usuario.
     * Para columnas simples preferir {@see sqlFoldedLowerColumn()}, que sí valida.
     *
     * @param  string $expression  Trusted SQL expression.
     * @param  string $driver      `'pgsql'` or `'sqlite'`.
     *
     * @return array{0: string, 1: list<string>}
     *
     * @throws \InvalidArgumentException for unsupported drivers.
     */
    public static function sqlFoldedLowerExpression(string $expression, string $driver): array
    {
        return match ($driver) {
            'pgsql'  => self::pgsqlFoldedExpr($expression),
            'sqlite' => ["lower({$expression})", []],
            default  => throw new \InvalidArgumentException(
                "AccentFold::sqlFoldedLowerColumn() does not support driver '{$driver}'. Supported: pgsql, sqlite."
            ),
        };
    }

    /**
     * El nombre de columna se interpola en SQL crudo: solo identificadores
     * simples (con esquema/tabla opcional via punto). Nunca pasar input de usuario.
     *
     * @throws \InvalidArgumentException si el identificador no es seguro.
     */
    private static function assertSafeColumnIdentifier(string $column): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*){0,2}$/', $column)) {
            throw new \InvalidArgumentException("AccentFold: identificador de columna inseguro: {$column}");
        }
    }

    /**
     * Build the translate + replace chain for PostgreSQL.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function pgsqlFoldedExpr(string $column): array
    {
        $map = self::charMap();
        ksort($map, SORT_STRING);

        $from = '';
        $to   = '';
        foreach ($map as $f => $t) {
            $from .= $f;
            $to   .= $t;
        }

        // translate handles 1:1 char pairs; replace chain handles multi-char expansions
        $expr = "translate(lower({$column}), ?, ?)";
        $expr = "replace(replace(replace(replace({$expr}, 'œ', 'oe'), 'æ', 'ae'), 'ß', 'ss'), 'ẞ', 'ss')";

        return [$expr, [$from, $to]];
    }
}
