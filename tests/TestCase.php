<?php

declare(strict_types=1);

namespace Tests;

use AssertionError;
use Throwable;

/**
 * What every test class extends. bin/test.php runs each public method whose name starts with "test"
 * on a new object, and a test fails when it throws.
 */
abstract class TestCase
{
    /** The same value and the same type; arrays also need the same keys in the same order. */
    protected function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new AssertionError(sprintf("expected %s\n     got      %s", self::show($expected), self::show($actual)));
        }
    }

    /** @param class-string<Throwable> $class what the code must throw, or a class that extends it */
    protected function assertThrows(string $class, callable $code): void
    {
        try {
            $code();
        } catch (Throwable $error) {
            if ($error instanceof $class) {
                return;
            }

            throw new AssertionError(sprintf('expected %s, got %s: %s', $class, $error::class, $error->getMessage()));
        }

        throw new AssertionError("expected $class, but nothing was thrown");
    }

    private static function show(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: get_debug_type($value);
    }
}
