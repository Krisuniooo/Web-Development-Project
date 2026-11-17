<?php

declare(strict_types=1);

namespace Tests;

use App\Secrets;
use RuntimeException;

/** Sealing the tokens that go into the database. */
final class SecretsTest extends TestCase
{
    private const TOKEN = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5';

    public function testSealedSecretOpensToTheSameSecret(): void
    {
        $secrets = new Secrets('the-app-key');

        $this->assertSame(self::TOKEN, $secrets->open($secrets->seal(self::TOKEN)));
    }

    public function testSealedTextHoldsNothingOfTheSecret(): void
    {
        $sealed = (new Secrets('the-app-key'))->seal(self::TOKEN);

        $this->assertSame(false, str_contains($sealed, self::TOKEN));
        $this->assertSame(false, str_contains(base64_decode($sealed, true), self::TOKEN));
    }

    public function testTheSameSecretSealedTwiceLooksDifferent(): void
    {
        $secrets = new Secrets('the-app-key');

        $this->assertSame(true, $secrets->seal(self::TOKEN) !== $secrets->seal(self::TOKEN));
    }

    public function testAnotherKeyCannotOpenIt(): void
    {
        $sealed = (new Secrets('the-app-key'))->seal(self::TOKEN);

        $this->assertThrows(RuntimeException::class, fn() => (new Secrets('another-key'))->open($sealed));
    }

    public function testChangedTextCannotBeOpened(): void
    {
        $secrets = new Secrets('the-app-key');
        $bytes = base64_decode($secrets->seal(self::TOKEN), true);
        // one bit of the sealed bytes, which would otherwise pass for another secret
        $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);

        $this->assertThrows(RuntimeException::class, fn() => $secrets->open(base64_encode($bytes)));
    }

    public function testTextNotSealedByTheAppIsRefused(): void
    {
        $secrets = new Secrets('the-app-key');

        $this->assertThrows(RuntimeException::class, fn() => $secrets->open(''));
        $this->assertThrows(RuntimeException::class, fn() => $secrets->open('not base64 !!'));
        // too short to hold even the number and the check
        $this->assertThrows(RuntimeException::class, fn() => $secrets->open(base64_encode('short')));
    }

    public function testEmptySecretCanBeSealed(): void
    {
        $secrets = new Secrets('the-app-key');

        $this->assertSame('', $secrets->open($secrets->seal('')));
    }

    public function testWithoutAKeyNothingIsSealed(): void
    {
        $this->assertThrows(RuntimeException::class, fn() => new Secrets(''));
    }
}
