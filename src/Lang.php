<?php

declare(strict_types=1);

namespace App;

final class Lang
{
    public const SUPPORTED = ['en', 'pl'];
    public const COOKIE = 'lang';
    private const FALLBACK = 'en';

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

    public function code(): string
    {
        return $this->code;
    }

    public function text(string $key): string
    {
        return $this->messages[$key] ?? $key;
    }

    /** The same text, safe to print in HTML. */
    public function html(string $key): string
    {
        return htmlspecialchars($this->text($key), ENT_QUOTES, 'UTF-8');
    }

    private static function load(string $code): array
    {
        return require dirname(__DIR__) . '/config/lang/' . $code . '.php';
    }
}
