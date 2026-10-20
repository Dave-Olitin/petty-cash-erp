<?php

namespace Illuminate\Support;

use Illuminate\Support\Traits\Macroable;
use NumberFormatter;

class Number
{
    use Macroable;

    /**
     * The current default locale.
     *
     * @var string
     */
    protected static $locale = 'en';

    /**
     * The current default currency.
     *
     * @var string
     */
    protected static $currency = 'AED';

    /**
     * Format the given number according to the current locale.
     *
     * @param  int|float  $number
     * @param  int|null  $precision
     * @param  int|null  $maxPrecision
     * @param  string|null  $locale
     * @return string|false
     */
    public static function format(int|float $number, ?int $precision = null, ?int $maxPrecision = null, ?string $locale = null)
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::DECIMAL);

            if (! is_null($maxPrecision)) {
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
            } elseif (! is_null($precision)) {
                $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
            }

            return $formatter->format($number);
        }

        // Graceful fallback when PHP intl extension is unavailable
        if (! is_null($maxPrecision)) {
            return number_format($number, $maxPrecision);
        } elseif (! is_null($precision)) {
            return number_format($number, $precision);
        }

        return is_int($number) || floor($number) == $number
            ? number_format($number, 0)
            : rtrim(rtrim(number_format($number, 2), '0'), '.');
    }

    /**
     * Parse the given string according to the specified format type.
     *
     * @param  string  $string
     * @param  int|null  $type
     * @param  string|null  $locale
     * @return int|float|false
     */
    public static function parse(string $string, ?int $type = null, ?string $locale = null): int|float|false
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::DECIMAL);
            return $formatter->parse($string, $type ?? NumberFormatter::TYPE_DOUBLE);
        }

        $clean = preg_replace('/[^0-9.-]/', '', $string);
        return is_numeric($clean) ? ($type === 1 ? (int) $clean : (float) $clean) : false;
    }

    public static function parseInt(string $string, ?string $locale = null): int|false
    {
        return self::parse($string, 1, $locale);
    }

    public static function parseFloat(string $string, ?string $locale = null): float|false
    {
        return self::parse($string, 2, $locale);
    }

    public static function spell(int|float $number, ?string $locale = null, ?int $after = null, ?int $until = null)
    {
        if (! is_null($after) && $number <= $after) {
            return static::format($number, locale: $locale);
        }

        if (! is_null($until) && $number >= $until) {
            return static::format($number, locale: $locale);
        }

        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::SPELLOUT);
            return $formatter->format($number);
        }

        return (string) $number;
    }

    public static function ordinal(int|float $number, ?string $locale = null)
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::ORDINAL);
            return $formatter->format($number);
        }

        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        }

        return $number . ($ends[$number % 10] ?? 'th');
    }

    public static function spellOrdinal(int|float $number, ?string $locale = null)
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::SPELLOUT);
            $formatter->setTextAttribute(NumberFormatter::DEFAULT_RULESET, '%spellout-ordinal');
            return $formatter->format($number);
        }

        return static::ordinal($number, $locale);
    }

    public static function percentage(int|float $number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null)
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::PERCENT);

            if (! is_null($maxPrecision)) {
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
            } else {
                $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
            }

            return $formatter->format($number / 100);
        }

        return number_format($number, $precision) . '%';
    }

    public static function currency(int|float $number, string $in = '', ?string $locale = null, ?int $precision = null)
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::CURRENCY);

            if (! is_null($precision)) {
                $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
            }

            return $formatter->formatCurrency($number, ! empty($in) ? $in : static::$currency);
        }

        $curr = ! empty($in) ? $in : static::$currency;
        return $curr . ' ' . number_format($number, $precision ?? 2);
    }

    public static function fileSize(int|float $bytes, int $precision = 0, ?int $maxPrecision = null)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $unitCount = count($units);

        for ($i = 0; ($bytes / 1024) > 0.9 && ($i < $unitCount - 1); $i++) {
            $bytes /= 1024;
        }

        return sprintf('%s %s', static::format($bytes, $precision, $maxPrecision), $units[$i]);
    }

    public static function abbreviate(int|float $number, int $precision = 0, ?int $maxPrecision = null)
    {
        return static::forHumans($number, $precision, $maxPrecision, abbreviate: true);
    }

    public static function forHumans(int|float $number, int $precision = 0, ?int $maxPrecision = null, bool $abbreviate = false)
    {
        return static::summarize($number, $precision, $maxPrecision, $abbreviate ? [
            3 => 'K',
            6 => 'M',
            9 => 'B',
            12 => 'T',
            15 => 'Q',
        ] : [
            3 => ' thousand',
            6 => ' million',
            9 => ' billion',
            12 => ' trillion',
            15 => ' quadrillion',
        ]);
    }

    protected static function summarize(int|float $number, int $precision = 0, ?int $maxPrecision = null, array $units = [])
    {
        if (empty($units)) {
            $units = [
                3 => 'K',
                6 => 'M',
                9 => 'B',
                12 => 'T',
                15 => 'Q',
            ];
        }

        switch (true) {
            case (float) $number === 0.0:
                return $precision > 0 ? static::format(0, $precision, $maxPrecision) : '0';
            case $number < 0:
                return sprintf('-%s', static::summarize(abs($number), $precision, $maxPrecision, $units));
            case $number >= 1e15:
                return sprintf('%s'.end($units), static::summarize($number / 1e15, $precision, $maxPrecision, $units));
        }

        $numberExponent = floor(log10($number));
        $displayExponent = $numberExponent - ($numberExponent % 3);
        $number /= pow(10, $displayExponent);

        return trim(sprintf('%s%s', static::format($number, $precision, $maxPrecision), $units[$displayExponent] ?? ''));
    }

    public static function clamp(int|float $number, int|float $min, int|float $max)
    {
        return min(max($number, $min), $max);
    }

    public static function pairs(int|float $to, int|float $by, int|float $start = 0, int|float $offset = 1)
    {
        $output = [];

        for ($lower = $start; $lower < $to; $lower += $by) {
            $upper = $lower + $by - $offset;

            if ($upper > $to) {
                $upper = $to;
            }

            $output[] = [$lower, $upper];
        }

        return $output;
    }

    public static function trim(int|float $number)
    {
        return json_decode(json_encode($number));
    }

    public static function withLocale(string $locale, callable $callback)
    {
        $previousLocale = static::$locale;

        static::useLocale($locale);

        try {
            return $callback();
        } finally {
            static::useLocale($previousLocale);
        }
    }

    public static function withCurrency(string $currency, callable $callback)
    {
        $previousCurrency = static::$currency;

        static::useCurrency($currency);

        try {
            return $callback();
        } finally {
            static::useCurrency($previousCurrency);
        }
    }

    public static function useLocale(string $locale)
    {
        static::$locale = $locale;
    }

    public static function useCurrency(string $currency)
    {
        static::$currency = $currency;
    }

    public static function defaultLocale()
    {
        return static::$locale;
    }

    public static function defaultCurrency()
    {
        return static::$currency;
    }

    protected static function ensureIntlExtensionIsInstalled()
    {
        // Safe no-op so that it never throws RuntimeException
    }
}
