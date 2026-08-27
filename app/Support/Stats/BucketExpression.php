<?php

namespace App\Support\Stats;

use App\Enums\Granularity;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The SQL that turns a click's timestamp into a chart bucket key.
 *
 * Production runs MySQL and the suite runs SQLite, so both have to produce the
 * same key for the same instant, byte for byte, and both have to agree with
 * Granularity::keyFor().
 */
final readonly class BucketExpression
{
    private function __construct(
        private Granularity $granularity,
        private int $offsetMinutes,
        private string $driver,
    ) {}

    /**
     * Build the expression for a granularity on the given connection driver.
     */
    public static function for(Granularity $granularity, string $timezone, string $driver): self
    {
        return new self($granularity, CarbonImmutable::now($timezone)->utcOffset(), $driver);
    }

    /**
     * Get the SQL expression, ready to drop into a select and a group by.
     *
     * @return literal-string
     */
    public function sql(): string
    {
        return match ($this->driver) {
            'mysql', 'mariadb' => $this->mysql(),
            'sqlite' => $this->sqlite(),
            default => throw new RuntimeException(
                "Chart buckets are not implemented for the [{$this->driver}] driver.",
            ),
        };
    }

    /**
     * Get the bindings the expression expects, in order.
     *
     * The count is read back off the SQL rather than tracked separately, so a
     * dialect that has to name the shifted timestamp twice cannot drift out of
     * step with the values supplied for it.
     *
     * @return array<int, int|string>
     */
    public function bindings(): array
    {
        $offset = $this->driver === 'sqlite'
            ? sprintf('%+d minutes', $this->offsetMinutes)
            : $this->offsetMinutes;

        return array_fill(0, substr_count($this->sql(), '?'), $offset);
    }

    /**
     * Build the MySQL flavour of the expression.
     *
     * @return literal-string
     */
    private function mysql(): string
    {
        // The timezone arrives as a plain minute interval rather than through
        // CONVERT_TZ, which returns NULL unless MySQL's timezone tables have
        // been loaded — they are not, on the production server.
        $shifted = 'DATE_ADD(created_at, INTERVAL ? MINUTE)';

        return match ($this->granularity) {
            Granularity::HOUR => "DATE_FORMAT({$shifted}, '%Y-%m-%d %H:00')",
            Granularity::DAY => "DATE_FORMAT({$shifted}, '%Y-%m-%d')",
            // Anchored to the Monday date, never to a week number: MySQL's %u
            // and SQLite's %W disagree by one for the same week.
            Granularity::WEEK => "DATE_FORMAT(DATE_SUB({$shifted}, INTERVAL WEEKDAY({$shifted}) DAY), '%Y-%m-%d')",
            Granularity::MONTH => "DATE_FORMAT({$shifted}, '%Y-%m')",
        };
    }

    /**
     * Build the SQLite flavour of the expression.
     *
     * @return literal-string
     */
    private function sqlite(): string
    {
        $shifted = 'datetime(created_at, ?)';

        return match ($this->granularity) {
            Granularity::HOUR => "strftime('%Y-%m-%d %H:00', {$shifted})",
            Granularity::DAY => "strftime('%Y-%m-%d', {$shifted})",
            // 'weekday 0' moves forward to the coming Sunday, or stays put if
            // it already is one; six days back from there is the Monday that
            // started the week.
            Granularity::WEEK => "date({$shifted}, 'weekday 0', '-6 days')",
            Granularity::MONTH => "strftime('%Y-%m', {$shifted})",
        };
    }
}
