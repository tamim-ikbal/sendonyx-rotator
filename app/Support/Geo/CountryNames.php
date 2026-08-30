<?php

namespace App\Support\Geo;

/**
 * Turns a stored country code back into a name a reader recognises.
 *
 * Clicks record ISO 3166-1 alpha-2 codes, because that is what the CDN header
 * and the local database both state, it is two bytes wide, and it survives a
 * country being renamed. The name is a display concern and is resolved here,
 * against the committed CLDR list, at the point something is shown.
 */
final class CountryNames
{
    /**
     * The list, kept for the life of the process once it has been read.
     *
     * @var array<string, string>|null
     */
    private static ?array $names = null;

    /**
     * Resolve the display name for a country code.
     *
     * An unknown code comes back as the code itself rather than as null: the
     * list can trail a newly issued code, and a dashboard showing `XK` is more
     * use than one showing nothing. Null and blank stay null, which is how an
     * unclassified click keeps reading as unclassified.
     */
    public static function name(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        return self::all()[$code] ?? $code;
    }

    /**
     * Get every country code with its name.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $path = config()->string('rotator.geo.countries');

        /** @var array<array-key, mixed> $list */
        $list = is_file($path) ? require $path : [];

        $names = [];

        foreach ($list as $code => $name) {
            if (is_string($code) && is_string($name)) {
                $names[strtoupper($code)] = $name;
            }
        }

        return self::$names = $names;
    }

    /**
     * Forget the list so the next read goes back to the file.
     */
    public static function flush(): void
    {
        self::$names = null;
    }
}
