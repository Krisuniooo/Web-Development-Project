<?php

declare(strict_types=1);

namespace App;

use Closure;
use CurlHandle;
use Throwable;

/**
 * The messages waiting for the model, kept only in the bot's memory, so their text never reaches the disk
 * unless the bot acts on the message. The bot adds messages as they come and calls tick() between Gateway events:
 * tick() sends a few of them to the model at a time without waiting, logs each decision without the text,
 * and hands a message that breaks a rule to $onBroken. A restart loses the messages that were waiting.
 */
final class ModerationQueue
{
    // how many messages the model checks at once for the bot; the rest wait here, so the time limit counts from sending
    private const PARALLEL_REQUESTS = 2;
    // a message that waited longer is skipped: acting on it so late would only confuse the chat
    private const MAX_WAIT_SECONDS = 90;
    // during a raid the oldest messages give way, so the memory stays small
    private const MAX_WAITING = 1000;
    // how soon tick() wants to run again while there is work
    private const BUSY_SECONDS = 0.05;

    /** @var list<array> the messages with their channel's settings and the time they came, oldest first */
    private array $waiting = [];

    // how many messages are with the model now
    private int $checking = 0;

    /** @var array<string, MessageClassifier> by model */
    private array $classifiers = [];

    /**
     * @param Closure(array, array<string, float>, list<string>): void $onBroken gets a message that breaks a rule,
     *     its scores and the categories whose rule it breaks
     */
    public function __construct(private PendingRequests $requests, private Closure $onBroken)
    {
    }

    /**
     * Puts a message at the end of the queue.
     *
     * @param array{id: string, channelId: string, authorId: string, authorName: string, text: string} $message
     * @param array{channelId: int, name: string, model: string, language: string, rules: array<string, array{level: string, action: string}>} $settings
     *     of its channel, as ChannelRepository::moderationSettings() gives them
     */
    public function add(array $message, array $settings): void
    {
        if (count($this->waiting) >= self::MAX_WAITING) {
            $oldest = array_shift($this->waiting);
            DiscordGateway::log("Message {$oldest['id']} not checked: the queue is full");
        }

        $this->waiting[] = $message + ['settings' => $settings, 'addedAt' => microtime(true)];
    }

    /**
     * Sends waiting messages while there is room and moves all requests on, never waiting.
     * Returns how many seconds may pass before the next call, or null when there is nothing to do.
     */
    public function tick(): ?float
    {
        $this->sendWaiting();
        $isBusy = $this->requests->tick();

        return $this->waiting === [] && !$isBusy ? null : self::BUSY_SECONDS;
    }

    private function sendWaiting(): void
    {
        while ($this->checking < self::PARALLEL_REQUESTS && $this->waiting !== []) {
            $message = array_shift($this->waiting);
            $waited = microtime(true) - $message['addedAt'];

            if ($waited > self::MAX_WAIT_SECONDS) {
                DiscordGateway::log(sprintf('Message %s not checked: it waited %d s', $message['id'], $waited));
                continue;
            }

            try {
                $settings = $message['settings'];
                $request = $this->classifier($settings['model'])->scoresRequest($message['text'], $settings['language']);
            } catch (Throwable $error) {
                $this->logFailure($message, 'not checked', $error);
                continue;
            }

            $this->checking++;
            $sentAt = microtime(true);
            $this->requests->send(
                $request,
                fn(CurlHandle $done, ?string $response) => $this->finish($message, $done, $response, $sentAt),
            );
        }
    }

    /** Reads the model's answer for one message, logs the decision and passes on a broken rule; an error costs only this message. */
    private function finish(array $message, CurlHandle $request, ?string $response, float $sentAt): void
    {
        $this->checking--;
        $settings = $message['settings'];
        $levels = array_map(fn(array $rule): string => $rule['level'], $settings['rules']);

        try {
            $classifier = $this->classifier($settings['model']);
            $scores = $classifier->readScores($request, $response, $settings['language']);
            $broken = $classifier->brokenRules($scores, $settings['language'], $levels);
        } catch (Throwable $error) {
            $this->logFailure($message, 'not checked', $error);
            return;
        }

        DiscordGateway::log(sprintf(
            'Message %s in channel %s: %s | %s | waited %.1f s, checked in %.1f s',
            $message['id'],
            $message['channelId'],
            $broken === []
                ? 'allowed'
                : 'breaks ' . implode(', ', array_map(fn(string $category): string => "$category ({$levels[$category]})", $broken)),
            implode(', ', array_map(fn(string $category, float $score): string => sprintf('%s %.2f', $category, $score), array_keys($scores), $scores)),
            $sentAt - $message['addedAt'],
            microtime(true) - $sentAt,
        ));

        if ($broken === []) {
            return;
        }

        try {
            ($this->onBroken)($message, $scores, $broken);
        } catch (Throwable $error) {
            $this->logFailure($message, 'not acted on', $error);
        }
    }

    private function classifier(string $model): MessageClassifier
    {
        return $this->classifiers[$model] ??= new MessageClassifier($model);
    }

    /** The reason names the model, the service or the status, never the text of the message. */
    private function logFailure(array $message, string $what, Throwable $error): void
    {
        DiscordGateway::log(sprintf('Message %s %s: %s', $message['id'], $what, preg_replace('/\s+/', ' ', $error->getMessage())));
    }
}
