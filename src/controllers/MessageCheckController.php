<?php

declare(strict_types=1);

namespace App\Controller;

use App\Moderation\MessageClassifier;
use RuntimeException;

/** The test box of the panel: the page's script asks whether the model works and what it says about a message. */
final class MessageCheckController extends AbstractPanelController
{
    // per login, because the bot uses the same model
    private const CHECKS_LIMIT = 10;
    private const LIMIT_SECONDS = 60;
    // how exact a score is sent to the page, which shows it as a whole percent
    private const SCORE_DECIMALS = 4;

    /** Whether the model can check a message now. */
    public function modelStatus(): void
    {
        if ($this->loggedInUser() === null) {
            $this->json(['error' => $this->lang()->text('check.error.login')], 401);
            return;
        }

        // the session is only read here, so the user's other tabs need not wait for the model
        session_write_close();

        if (!$this->testClassifier()->isReady()) {
            $this->json(['ready' => false, 'error' => $this->lang()->text('check.error.model')], 503);
            return;
        }

        $this->json(['ready' => true]);
    }

    /** What the model says about a message, and what each level would decide. */
    public function check(): void
    {
        $lang = $this->lang();

        if (!$this->isSameOriginRequest()) {
            $this->json(['error' => $lang->text('check.error.forbidden')], 403);
            return;
        }

        $user = $this->loggedInUser();

        if ($user === null) {
            $this->json(['error' => $lang->text('check.error.login')], 401);
            return;
        }

        // the same rule as the sections: a chosen server that still has the bot and that the user may still manage
        if ($this->access()->managedServer($user) === null) {
            $this->json(['error' => $lang->text('check.error.server')], 403);
            return;
        }

        $classifier = $this->testClassifier();
        $request = json_decode((string) file_get_contents('php://input'), true);
        $text = is_array($request) ? ($request['text'] ?? null) : null;
        $language = is_array($request) ? ($request['language'] ?? null) : null;

        if (!is_string($text) || trim($text) === '' || mb_strlen($text) > self::TEST_MAX_LENGTH) {
            $this->json(['error' => sprintf($lang->text('check.error.text'), self::TEST_MAX_LENGTH)], 400);
            return;
        }

        if (!in_array($language, $classifier->languages(), true)) {
            $this->json(['error' => $lang->text('check.error.language')], 400);
            return;
        }

        // a request turned down above does not count
        $secondsToWait = $this->countCheck();

        if ($secondsToWait !== null) {
            header('Retry-After: ' . $secondsToWait);
            $this->json(['error' => $lang->text('check.error.limit')], 429);
            return;
        }

        // the model takes seconds, and an open session would make the user's other tabs wait
        session_write_close();
        // nor is the database needed any more
        $this->closeDatabase();

        try {
            $scores = $classifier->scores($text, $language);
        } catch (RuntimeException $exception) {
            error_log('Panel could not check a message: ' . $exception->getMessage());
            $this->json(['error' => $lang->text('check.error.model')], 503);
            return;
        }

        $this->json(['categories' => $this->verdicts($classifier, $scores, $language)]);
    }

    /**
     * Counts one more check of this login, unless it already made as many as the limit allows in the limit's time.
     * Returns null when the check may go on, or else how many seconds to wait.
     */
    private function countCheck(): ?int
    {
        $now = time();
        $times = is_array($_SESSION['messageChecks'] ?? null) ? $_SESSION['messageChecks'] : [];
        $recent = array_values(array_filter($times, fn(mixed $time): bool => is_int($time) && $time > $now - self::LIMIT_SECONDS));

        if (count($recent) >= self::CHECKS_LIMIT) {
            // the oldest of them leaves the limit's time first
            return $recent[0] + self::LIMIT_SECONDS - $now;
        }

        $_SESSION['messageChecks'] = [...$recent, $now];

        return null;
    }

    /**
     * Each category with the model's score and, by level, whether the message breaks its rule there.
     *
     * @param array<string, float> $scores
     * @return list<array{key: string, name: string, score: float, breaks: array<string, bool>}>
     */
    private function verdicts(MessageClassifier $classifier, array $scores, string $language): array
    {
        $brokenByLevel = $classifier->brokenRulesByLevel($scores, $language);
        $categories = [];

        foreach ($scores as $category => $score) {
            $categories[] = [
                'key' => $category,
                'name' => $this->lang()->text('category.' . $category),
                'score' => round($score, self::SCORE_DECIMALS),
                'breaks' => array_map(fn(array $broken): bool => in_array($category, $broken, true), $brokenByLevel),
            ];
        }

        return $categories;
    }
}
