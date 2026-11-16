<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AppController;

/** What every controller inherits: the origin check that guards every form sent by POST, and when cookies are kept off plain HTTP. */
final class AppControllerTest extends TestCase
{
    public function testFormFromThisSiteIsAccepted(): void
    {
        $this->assertSame(true, self::isSameOrigin(['HTTP_SEC_FETCH_SITE' => 'same-origin']));
    }

    public function testFormFromAnotherSiteIsRefusedEvenWithAMatchingOrigin(): void
    {
        $this->assertSame(false, self::isSameOrigin(['HTTP_SEC_FETCH_SITE' => 'cross-site']));
        $this->assertSame(false, self::isSameOrigin(['HTTP_SEC_FETCH_SITE' => 'same-site']));
        $this->assertSame(false, self::isSameOrigin([
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
            'HTTP_ORIGIN' => 'https://chatwarden.example',
            'HTTP_HOST' => 'chatwarden.example',
        ]));
    }

    public function testWithoutSecFetchSiteTheOriginMustBeThisHost(): void
    {
        $this->assertSame(true, self::isSameOrigin(['HTTP_ORIGIN' => 'https://chatwarden.example', 'HTTP_HOST' => 'chatwarden.example']));
        $this->assertSame(false, self::isSameOrigin(['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_HOST' => 'chatwarden.example']));
        $this->assertSame(false, self::isSameOrigin(['HTTP_ORIGIN' => 'https://chatwarden.example.evil.example', 'HTTP_HOST' => 'chatwarden.example']));
    }

    public function testRequestWithNeitherHeaderIsAccepted(): void
    {
        // browsers send one of the two on every form, so such a request carries no visitor's cookies
        $this->assertSame(true, self::isSameOrigin([]));
    }

    public function testRequestOverHttpsKeepsCookiesOffPlainHttp(): void
    {
        $this->assertSame(true, self::isHttps(['HTTPS' => 'on'], 'http://localhost:8080'));
    }

    public function testHttpsAddressBehindAProxyKeepsCookiesOffPlainHttp(): void
    {
        // the proxy ends the encrypted connection, so PHP itself sees plain HTTP
        $this->assertSame(true, self::isHttps([], 'https://chatwarden.example'));
    }

    public function testPlainHttpSiteStillGetsItsCookies(): void
    {
        $this->assertSame(false, self::isHttps([], 'http://localhost:8080'));
        $this->assertSame(false, self::isHttps([], ''));
    }

    private static function isSameOrigin(array $headers): bool
    {
        return self::withServer(
            ['HTTP_SEC_FETCH_SITE', 'HTTP_ORIGIN', 'HTTP_HOST'],
            $headers,
            fn(): bool => (new ExposedController())->sameOrigin(),
        );
    }

    /** Runs the check with this APP_URL, then puts the real one back. */
    private static function isHttps(array $server, string $appUrl): bool
    {
        $saved = getenv('APP_URL');
        putenv('APP_URL=' . $appUrl);

        try {
            return self::withServer(['HTTPS'], $server, fn(): bool => (new ExposedController())->https());
        } finally {
            putenv($saved === false ? 'APP_URL' : 'APP_URL=' . $saved);
        }
    }

    /** Runs the check with only these values under the keys, leaving $_SERVER as it was. */
    private static function withServer(array $keys, array $values, callable $check): bool
    {
        $saved = array_intersect_key($_SERVER, array_flip($keys));

        try {
            foreach ($keys as $key) {
                unset($_SERVER[$key]);
            }

            foreach ($values as $key => $value) {
                $_SERVER[$key] = $value;
            }

            return $check();
        } finally {
            foreach ($keys as $key) {
                unset($_SERVER[$key]);
            }

            foreach ($saved as $key => $value) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

/** Opens the protected checks to the tests. */
final class ExposedController extends AppController
{
    public function sameOrigin(): bool
    {
        return $this->isSameOriginRequest();
    }

    public function https(): bool
    {
        return $this->isHttps();
    }
}
