<?php

declare(strict_types=1);

namespace App\Discord;

use App\Database;
use App\Log;
use App\Model\ModerationEvent;
use App\Moderation\PendingRequests;
use App\Repository\ChannelRepository;
use App\Repository\ModerationEventRepository;
use CurlHandle;
use PDO;
use Throwable;

/**
 * Acts on a Discord message that breaks a rule of its channel: picks the rule with the strongest action,
 * deletes the message without waiting and saves the event once the action is done, or once it failed.
 * It also counts every message the model checked, so the panel can show that the bot works.
 */
final class DiscordModerator
{
    // when a message breaks several rules, the strongest action wins
    private const STRENGTH = ['flag' => 1, 'delete' => 2, 'timeout' => 3, 'ban' => 4];
    // the longest reason of a failed action that is kept
    private const FAILURE_LENGTH = 200;

    private ?PDO $pdo = null;

    public function __construct(
        private PendingRequests $requests,
        private DiscordBotClient $discord,
        private Database $database,
    ) {
    }

    /**
     * @param array $message from ModerationQueue, with the settings of its channel
     * @param array<string, float> $scores
     * @param list<string> $broken the categories whose rule the message breaks
     */
    public function act(array $message, array $scores, array $broken): void
    {
        $rules = $message['settings']['rules'];
        // the strongest action first, and for the same action the rule the model is surest about
        usort($broken, fn(string $a, string $b): int => [self::STRENGTH[$rules[$b]['action']], $scores[$b]]
            <=> [self::STRENGTH[$rules[$a]['action']], $scores[$a]]);
        $category = $broken[0];

        if ($rules[$category]['action'] === 'flag') {
            Log::line(sprintf('Message %s flagged for %s', $message['id'], $category));
            $this->save($message, $category, 'flag', $scores[$category]);
            return;
        }

        // timeout and ban come with their settings in the panel; until then they delete the message too
        $this->requests->send(
            $this->discord->deleteMessageRequest($message['channelId'], $message['id']),
            function (CurlHandle $request, ?string $response) use ($message, $category, $scores): void {
                try {
                    DiscordBotClient::checkDeleted($request, $response);
                } catch (Throwable $error) {
                    Log::line(sprintf('Message %s not deleted: %s', $message['id'], $error->getMessage()));
                    // saved too, with Discord's reason, so the panel shows that the message is still there and why
                    $this->save($message, $category, 'delete', $scores[$category], $error->getMessage());
                    return;
                }

                Log::line(sprintf('Message %s deleted for %s', $message['id'], $category));
                $this->save($message, $category, 'delete', $scores[$category]);
            },
        );
    }

    /** Counts a message the model checked, whether it broke a rule or not. Only the number is kept. */
    public function countChecked(array $message): void
    {
        try {
            (new ChannelRepository($this->pdo()))->countCheck($message['settings']['channelId']);
        } catch (Throwable $error) {
            $this->pdo = null;
            Log::line(sprintf('Message %s: the check was not counted: %s', $message['id'], strtok($error->getMessage(), "\n")));
        }
    }

    /** Saves the event with the text, so the logs show what was moderated; $failure is why the action did not happen. */
    private function save(array $message, string $category, string $action, float $score, ?string $failure = null): void
    {
        $settings = $message['settings'];

        try {
            (new ModerationEventRepository($this->pdo()))->add(new ModerationEvent(
                null,
                $settings['channelId'],
                $message['channelId'],
                $settings['name'],
                $message['id'],
                $message['authorId'],
                $message['authorName'],
                $category,
                $settings['rules'][$category]['level'],
                $settings['model'],
                $action,
                $message['text'],
                $score,
                null,
                $failure === null ? null : mb_substr($failure, 0, self::FAILURE_LENGTH),
            ));
        } catch (Throwable $error) {
            $this->pdo = null;
            // only the first line: the database adds the whole row after it, and that holds the text
            Log::line(sprintf('Message %s: the event was not saved: %s', $message['id'], strtok($error->getMessage(), "\n")));
        }
    }

    /** Connects at the first use, and again after an error, so a broken connection is not kept. */
    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->connect();
    }
}
