<?php

declare(strict_types=1);

namespace Tests;

use App\Lang;

/** The texts of the interface, and the versions some of them have for one platform. */
final class LangTest extends TestCase
{
    public function testATextWithAVersionForThePlatformIsShownInItAfterThePlatformIsChosen(): void
    {
        $lang = $this->english();
        $plain = $lang->text('servers.title');

        $lang->usePlatform('twitch');

        $this->assertSame(true, $lang->text('servers.title') !== $plain);
        // the version is a text of its own, not a change of the plain one
        $this->assertSame($plain, $this->english()->text('servers.title'));
    }

    public function testATextWithoutAVersionForThePlatformStaysAsItIs(): void
    {
        $lang = $this->english();
        $plain = $lang->text('nav.overview');

        $lang->usePlatform('twitch');

        $this->assertSame($plain, $lang->text('nav.overview'));
    }

    public function testAPlatformWithoutVersionsChangesNothing(): void
    {
        $lang = $this->english();
        $plain = $lang->text('servers.title');

        $lang->usePlatform('discord');

        $this->assertSame($plain, $lang->text('servers.title'));
    }

    public function testMissingTextShowsItsKey(): void
    {
        $lang = $this->english();
        $lang->usePlatform('twitch');

        $this->assertSame('no.such.text', $lang->text('no.such.text'));
    }

    public function testTheHtmlFormIsEscaped(): void
    {
        $this->assertSame('no.such.&lt;text&gt;', $this->english()->html('no.such.<text>'));
    }

    /** The interface in English, whatever the cookie was; the cookie is put back at once. */
    private function english(): Lang
    {
        $cookies = $_COOKIE;
        $_COOKIE['lang'] = 'en';

        try {
            return Lang::fromRequest();
        } finally {
            $_COOKIE = $cookies;
        }
    }
}
