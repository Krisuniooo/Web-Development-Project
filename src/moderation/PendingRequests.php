<?php

declare(strict_types=1);

namespace App\Moderation;

use Closure;
use CurlHandle;
use CurlMultiHandle;

/**
 * The HTTP requests the bot sends without waiting for them, such as questions to the model and deletions on Discord.
 * tick() moves them all on at once and hands each finished one to its callback.
 */
final class PendingRequests
{
    private CurlMultiHandle $requests;

    /** @var array<int, Closure(CurlHandle, ?string): void> by the id of the request */
    private array $whenDone = [];

    public function __construct()
    {
        $this->requests = curl_multi_init();
    }

    /** @param Closure(CurlHandle, ?string): void $whenDone gets the finished request and what it returned; must not throw */
    public function send(CurlHandle $request, Closure $whenDone): void
    {
        curl_multi_add_handle($this->requests, $request);
        $this->whenDone[spl_object_id($request)] = $whenDone;
    }

    /** Moves the requests on and hands over the finished ones, never waiting. Returns whether any are still on the way. */
    public function tick(): bool
    {
        curl_multi_exec($this->requests, $stillRunning);

        while (($finished = curl_multi_info_read($this->requests)) !== false) {
            $request = $finished['handle'];
            $whenDone = $this->whenDone[spl_object_id($request)];
            unset($this->whenDone[spl_object_id($request)]);
            curl_multi_remove_handle($this->requests, $request);
            $whenDone($request, curl_multi_getcontent($request));
        }

        return $this->whenDone !== [];
    }
}
