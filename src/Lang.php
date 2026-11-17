<?php

declare(strict_types=1);

namespace App;

final class Lang
{
    public const SUPPORTED = ['en', 'pl'];
    private const COOKIE = 'lang';
    // a year, in seconds
    private const COOKIE_LIFETIME = 365 * 24 * 60 * 60;
    private const FALLBACK = 'en';

    // the platform whose own versions of the texts are shown, such as "twitch"
    private ?string $platform = null;

    private function __construct(private string $code, private array $messages)
    {
    }

    public static function fromRequest(): self
    {
        $code = $_COOKIE[self::COOKIE] ?? null;

        if (!in_array($code, self::SUPPORTED, true)) {
            $code = preg_match('/^pl\b/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') === 1 ? 'pl' : self::FALLBACK;
        }

        // a text missing from the chosen language is shown in the fallback one
        return new self($code, array_replace(self::load(self::FALLBACK), self::load($code)));
    }

    /** @param bool $isHttps whether the site is served over HTTPS, so the cookie can be kept off plain HTTP */
    public static function remember(mixed $code, bool $isHttps): void
    {
        if (in_array($code, self::SUPPORTED, true)) {
            setcookie(self::COOKIE, $code, [
                'expires' => time() + self::COOKIE_LIFETIME,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * From now on a text that has a version for this platform is shown in it. The version has the platform after
     * the key: "servers.title@twitch" says "channels" where "servers.title" says "servers".
     */
    public function usePlatform(string $platform): void
    {
        $this->platform = $platform;
    }

    public function text(string $key): string
    {
        return $this->messages["$key@{$this->platform}"] ?? $this->messages[$key] ?? $key;
    }

    /** The same text, safe to print in HTML. */
    public function html(string $key): string
    {
        return htmlspecialchars($this->text($key), ENT_QUOTES, 'UTF-8');
    }

    private static function load(string $code): array
    {
        return Config::load('lang/' . $code);
    }
}
