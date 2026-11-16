<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AppController;

/** The origin check that guards every form sent by POST. */
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

    /** Runs the check with only these headers, leaving $_SERVER as it was. */
    private static function isSameOrigin(array $headers): bool
    {
        $keys = ['HTTP_SEC_FETCH_SITE', 'HTTP_ORIGIN', 'HTTP_HOST'];
        $saved = array_intersect_key($_SERVER, array_flip($keys));

        try {
            foreach ($keys as $key) {
                unset($_SERVER[$key]);
            }

            foreach ($headers as $key => $value) {
                $_SERVER[$key] = $value;
            }

            return (new OriginCheckingController())->check();
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

final class OriginCheckingController extends AppController
{
    public function check(): bool
    {
        return $this->isSameOriginRequest();
    }
}
