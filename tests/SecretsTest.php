<?php

declare(strict_types=1);

namespace Tests;

use App\Secrets;
use RuntimeException;

/** Sealing the tokens that go into the database. */
final class SecretsTest extends TestCase
{
    private const TOKEN = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5';
    // made like APP_KEY: openssl rand -base64 32
    private const KEY = 'UfaeIAgoqbpiQ/jsLz4cgYzYdBlLW6f5SpGT3WKw5cM=';
    private const OTHER_KEY = '5y+mCh9s/WGz24PjODGO7qKxPyY4STiKeXgiGYj79I8=';

    public function testSealedSecretOpensToTheSameSecret(): void
    {
        $secrets = new Secrets(self::KEY);

        $this->assertSame(self::TOKEN, $secrets->open($secrets->seal(self::TOKEN)));
    }

    public function testSealedTextHoldsNothingOfTheSecret(): void
    {
        $sealed = (new Secrets(self::KEY))->seal(self::TOKEN);

        $this->assertSame(false, str_contains($sealed, self::TOKEN));
        $this->assertSame(false, str_contains(base64_decode($sealed, true), self::TOKEN));
    }

    public function testTheSameSecretSealedTwiceLooksDifferent(): void
    {
        $secrets = new Secrets(self::KEY);

        $this->assertSame(true, $secrets->seal(self::TOKEN) !== $secrets->seal(self::TOKEN));
    }

    public function testAnotherKeyCannotOpenIt(): void
    {
        $sealed = (new Secrets(self::KEY))->seal(self::TOKEN);

        $this->assertThrows(RuntimeException::class, fn() => (new Secrets(self::OTHER_KEY))->open($sealed));
    }

    public function testChangedTextCannotBeOpened(): void
    {
        $secrets = new Secrets(self::KEY);
        $bytes = base64_decode($secrets->seal(self::TOKEN), true);
        // one bit of the sealed bytes, which would otherwise pass for another secret
        $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);

        $this->assertThrows(RuntimeException::class, fn() => $secrets->open(base64_encode($bytes)));
    }

    public function testTextNotSealedByTheAppIsRefused(): void
    {
        $secrets = new Secrets(self::KEY);

        $this->assertThrows(RuntimeException::class, fn() => $secrets->open(''));
        $this->assertThrows(RuntimeException::class, fn() => $secrets->open('not base64 !!'));
        // too short to hold even the number and the check
        $this->assertThrows(RuntimeException::class, fn() => $secrets->open(base64_encode('short')));
    }

    public function testEmptySecretCanBeSealed(): void
    {
        $secrets = new Secrets(self::KEY);

        $this->assertSame('', $secrets->open($secrets->seal('')));
    }

    public function testWithoutAKeyNothingIsSealed(): void
    {
        $this->assertThrows(RuntimeException::class, fn() => new Secrets(''));
    }

    public function testAKeySomeoneCouldGuessIsRefused(): void
    {
        // a word, which is not base64 at all
        $this->assertThrows(RuntimeException::class, fn() => new Secrets('the-app-key'));
        // base64, but of a password
        $this->assertThrows(RuntimeException::class, fn() => new Secrets(base64_encode('secret123')));
        // one random byte fewer than needed
        $this->assertThrows(RuntimeException::class, fn() => new Secrets('VWe7MtZGXDfC/cFb5SKQeWWqMMGFSzQ6DUF2zcek6A=='));
    }
}
