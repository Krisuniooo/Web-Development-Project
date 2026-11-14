<?php

declare(strict_types=1);

namespace Tests;

use App\Moderation\Evaluation;

final class EvaluationTest extends TestCase
{
    public function testCountsCaughtViolationsAndBlockedCleanMessages(): void
    {
        $cases = [
            ['score' => 0.9, 'breaks' => true],
            ['score' => 0.4, 'breaks' => true],
            ['score' => 0.8, 'breaks' => false],
            ['score' => 0.1, 'breaks' => false],
            ['score' => 0.2, 'breaks' => false],
        ];

        $this->assertSame(['caught' => 1, 'violations' => 2, 'blocked' => 1, 'clean' => 3], Evaluation::count($cases, 0.7));
    }

    public function testScoreAtTheThresholdCounts(): void
    {
        $cases = [['score' => 0.7, 'breaks' => true]];

        $this->assertSame(1, Evaluation::count($cases, 0.7)['caught']);
    }

    public function testMistakesAddMissesAndBlocks(): void
    {
        $this->assertSame(3, Evaluation::mistakes(['caught' => 3, 'violations' => 5, 'blocked' => 1, 'clean' => 9]));
    }

    public function testBestThresholdSeparatesTheTwoGroups(): void
    {
        $cases = [
            ['score' => 0.92, 'breaks' => true],
            ['score' => 0.81, 'breaks' => true],
            ['score' => 0.62, 'breaks' => false],
            ['score' => 0.10, 'breaks' => false],
        ];

        // 0.65 to 0.80 all make no mistake; the highest of them blocks the fewest clean messages
        $this->assertSame(0.8, Evaluation::bestThreshold($cases));
    }

    public function testBestThresholdWithoutAnyViolationBlocksNothing(): void
    {
        $cases = [['score' => 0.3, 'breaks' => false], ['score' => 0.5, 'breaks' => false]];

        $this->assertSame(0.95, Evaluation::bestThreshold($cases));
    }

    public function testBestThresholdOfNoMessagesIsTheHighest(): void
    {
        $this->assertSame(0.95, Evaluation::bestThreshold([]));
    }
}
