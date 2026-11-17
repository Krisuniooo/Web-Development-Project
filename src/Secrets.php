<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Seals a secret before it goes into the database, such as the token a bot acts with, so a copy of the database
 * alone is not enough to use it. The key is made from APP_KEY in .env, which never goes into the database.
 * Whoever has both can read the secrets, so APP_KEY is kept as carefully as the database password.
 */
final class Secrets
{
    // tells this use of APP_KEY from any other, so the same key never makes the same bytes for two purposes
    private const KEY_PURPOSE = 'chatwarden secrets v1';
    // the fewest random bytes APP_KEY holds: the key is derived, not stretched, so a short or made-up one could be guessed
    private const MIN_KEY_BYTES = 32;

    private string $key;

    /** @throws RuntimeException when APP_KEY is not at least MIN_KEY_BYTES random bytes in base64 */
    public function __construct(string $appKey)
    {
        $keyBytes = base64_decode($appKey, true);

        if ($keyBytes === false || strlen($keyBytes) < self::MIN_KEY_BYTES) {
            throw new RuntimeException(
                'APP_KEY in .env must be at least ' . self::MIN_KEY_BYTES . ' random bytes in base64: make one with openssl rand -base64 32',
            );
        }

        // from the text of APP_KEY, as since the start, so the tokens sealed before still open
        $this->key = hash_hkdf('sha256', $appKey, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::KEY_PURPOSE, '');
    }

    public static function fromEnvironment(): self
    {
        return new self((string) getenv('APP_KEY'));
    }

    /** The secret, sealed and as text: a new random number first, then the sealed bytes, which also prove nobody changed them. */
    public function seal(string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, $this->key));
    }

    /** @throws RuntimeException when the text was changed, or sealed with another APP_KEY */
    public function open(string $sealed): string
    {
        $bytes = base64_decode($sealed, true);

        if ($bytes === false || strlen($bytes) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('The sealed secret is not in the form this app writes');
        }

        $secret = sodium_crypto_secretbox_open(
            substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        if ($secret === false) {
            throw new RuntimeException('The sealed secret cannot be opened: it was changed, or APP_KEY is not the one it was sealed with');
        }

        return $secret;
    }
}
