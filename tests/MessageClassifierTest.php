<?php

declare(strict_types=1);

namespace Tests;

use App\Moderation\MessageClassifier;
use RuntimeException;

/** The decision only: brokenRules() compares scores with thresholds and asks no model. */
final class MessageClassifierTest extends TestCase
{
    private const MODEL = 'basal-mini';
    private const LANGUAGE = 'en';

    public function testScoreAtTheThresholdBreaksTheRule(): void
    {
        $scores = ['racism' => $this->threshold('racism', 'balanced')];

        $this->assertSame(['racism'], $this->broken($scores, ['racism' => 'balanced']));
    }

    public function testScoreJustBelowTheThresholdIsAllowed(): void
    {
        $scores = ['racism' => $this->threshold('racism', 'balanced') - 0.001];

        $this->assertSame([], $this->broken($scores, ['racism' => 'balanced']));
    }

    public function testStricterLevelBlocksWhatARelaxedOneAllows(): void
    {
        $scores = ['harassment' => $this->threshold('harassment', 'balanced')];

        $this->assertSame(['harassment'], $this->broken($scores, ['harassment' => 'strict']));
        $this->assertSame([], $this->broken($scores, ['harassment' => 'relaxed']));
    }

    public function testRuleSetToOffIsSkippedEvenForTheHighestScore(): void
    {
        $this->assertSame([], $this->broken(['profanity' => 1.0], ['profanity' => 'off']));
    }

    public function testCategoryWithoutARuleIsSkipped(): void
    {
        $this->assertSame([], $this->broken(['profanity' => 1.0], []));
    }

    public function testOnlyTheBrokenRulesAreReturned(): void
    {
        $scores = ['racism' => 0.0, 'harassment' => 1.0, 'profanity' => 1.0];
        $levels = ['racism' => 'strict', 'harassment' => 'strict', 'profanity' => 'off'];

        $this->assertSame(['harassment'], $this->broken($scores, $levels));
    }

    public function testLanguageWithoutThresholdsThrows(): void
    {
        $classifier = new MessageClassifier(self::MODEL);

        $this->assertThrows(
            RuntimeException::class,
            fn() => $classifier->brokenRules(['racism' => 1.0], 'xx', ['racism' => 'balanced']),
        );
    }

    public function testUnknownLevelThrows(): void
    {
        $this->assertThrows(RuntimeException::class, fn() => $this->broken(['racism' => 1.0], ['racism' => 'harsh']));
    }

    public function testUnknownModelThrows(): void
    {
        $this->assertThrows(RuntimeException::class, fn() => new MessageClassifier('no-such-model'));
    }

    /**
     * @param array<string, float> $scores
     * @param array<string, string> $levels
     * @return list<string>
     */
    private function broken(array $scores, array $levels): array
    {
        return (new MessageClassifier(self::MODEL))->brokenRules($scores, self::LANGUAGE, $levels);
    }

    /** Read from the config, so tuning the thresholds does not break the tests. */
    private function threshold(string $category, string $level): float
    {
        $models = require dirname(__DIR__) . '/config/moderation.php';

        return $models[self::MODEL]['thresholds'][self::LANGUAGE][$category][$level];
    }
}
