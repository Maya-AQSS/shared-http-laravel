<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\In;
use Maya\Http\Http\Requests\PaginatedFilterRequest;

/** Subclase sin whitelist (retrocompatible). */
class NoWhitelistRequest extends PaginatedFilterRequest
{
    protected function filterRules(): array
    {
        return [];
    }
}

/** Subclase con whitelist de columnas ordenables. */
class WhitelistedSortRequest extends PaginatedFilterRequest
{
    protected function filterRules(): array
    {
        return [];
    }

    protected function allowedSortFields(): array
    {
        return ['name', 'created_at'];
    }
}

function makeReq(string $class, array $query): PaginatedFilterRequest
{
    /** @var PaginatedFilterRequest $req */
    $req = $class::create('/', 'GET', $query);

    return $req;
}

// ─── rules(): la whitelist añade Rule::in; sin whitelist no ──────────────────

it('does not add an In rule on sort_by when no whitelist is declared', function () {
    $rules = makeReq(NoWhitelistRequest::class, [])->rules();
    $hasIn = collect($rules['sort_by'])->contains(fn ($r) => $r instanceof In);
    expect($hasIn)->toBeFalse();
});

it('adds an In rule on sort_by when a whitelist is declared', function () {
    $rules = makeReq(WhitelistedSortRequest::class, [])->rules();
    $hasIn = collect($rules['sort_by'])->contains(fn ($r) => $r instanceof In);
    expect($hasIn)->toBeTrue();
});

// ─── getSortBy(): defensa en profundidad ─────────────────────────────────────

it('returns the column when it is in the whitelist', function () {
    expect(makeReq(WhitelistedSortRequest::class, ['sort_by' => 'name'])->getSortBy())->toBe('name');
});

it('returns null when sort_by is outside the whitelist (column injection guard)', function () {
    expect(makeReq(WhitelistedSortRequest::class, ['sort_by' => 'password; DROP TABLE'])->getSortBy())->toBeNull();
});

it('returns null when sort_by is absent', function () {
    expect(makeReq(WhitelistedSortRequest::class, [])->getSortBy())->toBeNull();
});

it('returns the raw value when no whitelist is declared (backward compatible)', function () {
    expect(makeReq(NoWhitelistRequest::class, ['sort_by' => 'anything'])->getSortBy())->toBe('anything');
});
