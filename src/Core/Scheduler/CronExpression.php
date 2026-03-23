<?php

namespace Fennec\Core\Scheduler;

class CronExpression
{
    /**
     * Verifie si une expression cron est due pour le moment donne.
     *
     * Format : minute hour day-of-month month day-of-week
     * Supporte : *, N, N-M, N,M, et star/N (ex: star/5)
     */
    public static function isDue(string $expression, \DateTimeInterface $now): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            throw new \InvalidArgumentException("Expression cron invalide : {$expression}");
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return self::matchField($minute, (int) $now->format('i'))
            && self::matchField($hour, (int) $now->format('G'))
            && self::matchField($dayOfMonth, (int) $now->format('j'))
            && self::matchField($month, (int) $now->format('n'))
            && self::matchField($dayOfWeek, (int) $now->format('w'));
    }

    private static function matchField(string $field, int $value): bool
    {
        // Wildcard
        if ($field === '*') {
            return true;
        }

        // Liste : 1,3,5
        if (str_contains($field, ',')) {
            $items = explode(',', $field);
            foreach ($items as $item) {
                if (self::matchField(trim($item), $value)) {
                    return true;
                }
            }

            return false;
        }

        // Step : */5 ou 1-30/5
        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field, 2);
            $step = (int) $step;

            if ($step <= 0) {
                return false;
            }

            if ($range === '*') {
                return $value % $step === 0;
            }

            // Range with step : 1-30/5
            if (str_contains($range, '-')) {
                [$min, $max] = explode('-', $range, 2);
                $min = (int) $min;
                $max = (int) $max;

                return $value >= $min && $value <= $max && ($value - $min) % $step === 0;
            }

            return $value % $step === 0;
        }

        // Range : 1-5
        if (str_contains($field, '-')) {
            [$min, $max] = explode('-', $field, 2);

            return $value >= (int) $min && $value <= (int) $max;
        }

        // Valeur exacte
        return (int) $field === $value;
    }
}
