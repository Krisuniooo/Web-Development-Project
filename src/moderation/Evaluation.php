<?php

declare(strict_types=1);

namespace App\Moderation;

/**
 * How well one threshold separates the messages that break a rule from the clean ones,
 * and which threshold does it best. Used by bin/measure.php on labelled messages.
 */
final class Evaluation
{
    // the thresholds tried: from the lowest to the highest, one step apart, as in the config
    private const LOWEST = 0.05;
    private const HIGHEST = 0.95;
    private const STEP = 0.05;

    /**
     * @param list<array{score: float, breaks: bool}> $cases one per message: the model's answer and whether it really breaks the rule
     * @return array{caught: int, violations: int, blocked: int, clean: int}
     *     caught of violations: those at or above the threshold; blocked of clean: the clean ones there by mistake
     */
    public static function count(array $cases, float $threshold): array
    {
        $result = ['caught' => 0, 'violations' => 0, 'blocked' => 0, 'clean' => 0];

        foreach ($cases as $case) {
            // decided as the bot decides
            $isAbove = MessageClassifier::breaks($case['score'], $threshold);

            if ($case['breaks']) {
                $result['violations']++;
                $result['caught'] += $isAbove ? 1 : 0;
            } else {
                $result['clean']++;
                $result['blocked'] += $isAbove ? 1 : 0;
            }
        }

        return $result;
    }

    /** Missed violations plus blocked clean messages. */
    public static function mistakes(array $count): int
    {
        return $count['violations'] - $count['caught'] + $count['blocked'];
    }

    /**
     * The threshold with the fewest mistakes. On a tie the higher one wins,
     * since it blocks fewer clean messages for the same number of mistakes.
     *
     * @param list<array{score: float, breaks: bool}> $cases
     */
    public static function bestThreshold(array $cases): float
    {
        $best = self::LOWEST;
        $fewest = PHP_INT_MAX;
        $steps = (int) round((self::HIGHEST - self::LOWEST) / self::STEP);

        for ($step = 0; $step <= $steps; $step++) {
            // rounded, so 0.70 is exactly the 0.70 of the config and not 0.7000000000000001
            $threshold = round(self::LOWEST + $step * self::STEP, 2);
            $mistakes = self::mistakes(self::count($cases, $threshold));

            if ($mistakes <= $fewest) {
                $best = $threshold;
                $fewest = $mistakes;
            }
        }

        return $best;
    }
}
