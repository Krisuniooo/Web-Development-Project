<?php

declare(strict_types=1);

namespace App\Moderation;

/**
 * How well one threshold separates the messages that break a rule from the clean ones,
 * and which threshold does it best. Used by bin/measure.php on labelled messages.
 */
final class Evaluation
{
    // the thresholds tried, as in the config: 0.05 apart
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
            // the same rule as MessageClassifier::brokenRules(); a little room, so 0.70 is not missed as 0.69999
            $isAbove = $case['score'] >= $threshold - 1e-9;

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
     * The threshold with the fewest mistakes, from 0.05 to 0.95. On a tie the higher one wins,
     * since it blocks fewer clean messages for the same number of mistakes.
     *
     * @param list<array{score: float, breaks: bool}> $cases
     */
    public static function bestThreshold(array $cases): float
    {
        $best = null;
        $fewest = PHP_INT_MAX;

        for ($step = 1; $step <= 19; $step++) {
            $threshold = round($step * self::STEP, 2);
            $mistakes = self::mistakes(self::count($cases, $threshold));

            if ($mistakes <= $fewest) {
                $best = $threshold;
                $fewest = $mistakes;
            }
        }

        return $best;
    }
}
