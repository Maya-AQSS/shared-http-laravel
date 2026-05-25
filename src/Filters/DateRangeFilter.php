<?php

declare(strict_types=1);

namespace Maya\Http\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Normaliza un par (from, to) ISO 8601 a UTC, dilata fechas-solo a end-of-day
 * para el campo `to` y valida que to ≥ from. Usado por los FormRequests que
 * filtran por rango temporal (audit listing, logs listing).
 */
class DateRangeFilter
{
    /**
     * @return array{0:?string,1:?string}
     *
     * @throws ValidationException
     */
    public static function normalize(
        ?string $from,
        ?string $to,
        string $fromField = 'dateFromInput',
        string $toField = 'dateToInput',
    ): array {
        $from = self::normalizeSingle($from, $fromField, false);
        $to   = self::normalizeSingle($to, $toField, true);

        if ($from !== null && $to !== null) {
            $fromDate = CarbonImmutable::parse($from);
            $toDate   = CarbonImmutable::parse($to);

            if ($toDate->lessThan($fromDate)) {
                throw ValidationException::withMessages([
                    $toField => __('validation.after_or_equal', ['attribute' => $toField, 'date' => $fromField]),
                ]);
            }
        }

        return [$from, $to];
    }

    /**
     * @throws ValidationException
     */
    private static function normalizeSingle(?string $value, string $field, bool $endOfDayIfDateOnly): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);

            if ($endOfDayIfDateOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1) {
                $date = $date->endOfDay();
            }

            return $date->utc()->toIso8601String();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => __('validation.date', ['attribute' => $field]),
            ]);
        }
    }
}
