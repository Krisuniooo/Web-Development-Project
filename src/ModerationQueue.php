<?php

declare(strict_types=1);

namespace App;

use CurlHandle;
use CurlMultiHandle;
use Throwable;

/**
 * The messages waiting for the model, kept only in the bot's memory, so their text never reaches the disk.
 * The bot adds messages as they come and calls tick() between Gateway events: tick() sends a few of them
 * to the model at a time without waiting, and logs each decision when its answer comes, never the text.
 * A restart loses the messages that were waiting.
 */
final class ModerationQueue
{
    // how many messages the model checks at once for the bot; the rest wait here, so the time limit counts from sending
    private const PARALLEL_REQUESTS = 2;
    // a message that waited longer is skipped: acting on it so late would only confuse the chat
    private const MAX_WAIT_SECONDS = 90;
    // during a raid the oldest messages give way, so the memory stays small
    private const MAX_WAITING = 1000;
    // how soon tick() wants to run again while a message waits or is being checked
    private const BUSY_SECONDS = 0.05;

    private CurlMultiHandle $requests;

    /** @var list<array{messageId: string, channelId: string, text: string, settings: array, addedAt: float}> oldest first */
    private array $waiting = [];

    /** @var array<int, array{message: array, request: CurlHandle, sentAt: float}> by the id of the request */
    private array $checking = [];

    /** @var array<string, MessageClassifier> by model */
    private array $classifiers = [];

    public function __construct()
    {
        $this->requests = curl_multi_init();
    }

    /**
     * Puts a message at the end of the queue.
     *
     * @param array{model: string, language: string, levels: array<string, string>} $settings of its channel
     */
    public function add(string $messageId, string $channelId, string $text, array $settings): void
    {
        if (count($this->waiting) >= self::MAX_WAITING) {
            $oldest = array_shift($this->waiting);
            DiscordGateway::log("Message {$oldest['messageId']} not checked: the queue is full");
        }

        $this->waiting[] = [
            'messageId' => $messageId,
            'channelId' => $channelId,
            'text' => $text,
            'settings' => $settings,
            'addedAt' => microtime(true),
        ];
    }

    /**
     * Sends waiting messages while there is room, moves the requests on and reads the finished ones. Never waits.
     * Returns how many seconds may pass before the next call, or null when there is nothing to do.
     */
    public function tick(): ?float
    {
        $this->sendWaiting();
        curl_multi_exec($this->requests, $stillRunning);

        while (($finished = curl_multi_info_read($this->requests)) !== false) {
            $this->finish($finished['handle']);
        }

        return $this->waiting === [] && $this->checking === [] ? null : self::BUSY_SECONDS;
    }

    private function sendWaiting(): void
    {
        while (count($this->checking) < self::PARALLEL_REQUESTS && $this->waiting !== []) {
            $message = array_shift($this->waiting);
            $waited = microtime(true) - $message['addedAt'];

            if ($waited > self::MAX_WAIT_SECONDS) {
                DiscordGateway::log(sprintf('Message %s not checked: it waited %d s', $message['messageId'], $waited));
                continue;
            }

            try {
                $settings = $message['settings'];
                $request = $this->classifier($settings['model'])->scoresRequest($message['text'], $settings['language']);
            } catch (Throwable $error) {
                $this->logFailure($message, $error);
                continue;
            }

            curl_multi_add_handle($this->requests, $request);
            $this->checking[spl_object_id($request)] = ['message' => $message, 'request' => $request, 'sentAt' => microtime(true)];
        }
    }

    /** Reads the model's answer for one message and logs the decision; an error costs only this message. */
    private function finish(CurlHandle $request): void
    {
        $id = spl_object_id($request);
        ['message' => $message, 'sentAt' => $sentAt] = $this->checking[$id];
        unset($this->checking[$id]);
        curl_multi_remove_handle($this->requests, $request);

        try {
            $settings = $message['settings'];
            $classifier = $this->classifier($settings['model']);
            $scores = $classifier->readScores($request, curl_multi_getcontent($request), $settings['language']);
            $broken = $classifier->brokenRules($scores, $settings['language'], $settings['levels']);
        } catch (Throwable $error) {
            $this->logFailure($message, $error);
            return;
        }

        DiscordGateway::log(sprintf(
            'Message %s in channel %s: %s | %s | waited %.1f s, checked in %.1f s',
            $message['messageId'],
            $message['channelId'],
            $broken === []
                ? 'allowed'
                : 'breaks ' . implode(', ', array_map(fn(string $category): string => "$category ({$settings['levels'][$category]})", $broken)),
            implode(', ', array_map(fn(string $category, float $score): string => sprintf('%s %.2f', $category, $score), array_keys($scores), $scores)),
            $sentAt - $message['addedAt'],
            microtime(true) - $sentAt,
        ));
    }

    private function classifier(string $model): MessageClassifier
    {
        return $this->classifiers[$model] ??= new MessageClassifier($model);
    }

    /** The message passes unchecked; the reason names the model or the status, never the text. */
    private function logFailure(array $message, Throwable $error): void
    {
        DiscordGateway::log(sprintf('Message %s not checked: %s', $message['messageId'], preg_replace('/\s+/', ' ', $error->getMessage())));
    }
}
