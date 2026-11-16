<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AppController;
use App\Routing;

final class RoutingTest extends TestCase
{
    public function testStaticPathRunsItsAction(): void
    {
        $this->assertSame(true, self::routing()->dispatch('GET', '/app/logs'));
        $this->assertSame(['logs'], RecordingController::$lastCall);
    }

    public function testIdInThePathReachesTheActionAsANumber(): void
    {
        $this->assertSame(true, self::routing()->dispatch('GET', '/app/logs/42'));
        $this->assertSame(['logEvent', 42], RecordingController::$lastCall);
    }

    public function testQueryStringDoesNotChangeTheRoute(): void
    {
        $this->assertSame(true, self::routing()->dispatch('GET', '/app/logs?channel=3&page=2'));
        $this->assertSame(['logs'], RecordingController::$lastCall);
    }

    public function testIdThatIsNotAWholeNumberMatchesNothing(): void
    {
        foreach (['/app/logs/abc', '/app/logs/-1', '/app/logs/1.5', '/app/logs/', '/app/logs/42/more'] as $path) {
            $this->assertSame(false, self::routing()->dispatch('GET', $path));
            $this->assertSame(null, RecordingController::$lastCall);
        }
    }

    public function testHeadIsAnsweredLikeGet(): void
    {
        $this->assertSame(true, self::routing()->dispatch('HEAD', '/app/logs/42'));
        $this->assertSame(['logEvent', 42], RecordingController::$lastCall);
    }

    public function testPathWithATrailingNewlineMatchesNothing(): void
    {
        $this->assertSame(false, self::routing()->dispatch('GET', "/app/logs\n"));
        $this->assertSame(false, self::routing()->dispatch('GET', "/app/logs/42\n"));
    }

    public function testOtherMethodOrUnknownPathMatchesNothing(): void
    {
        $this->assertSame(false, self::routing()->dispatch('POST', '/app/logs'));
        $this->assertSame(false, self::routing()->dispatch('GET', '/app/unknown'));
        $this->assertSame(null, RecordingController::$lastCall);
    }

    /** The same kinds of routes as in public/index.php, with a controller that only notes what ran. */
    private static function routing(): Routing
    {
        RecordingController::$lastCall = null;

        $routing = new Routing();
        $routing->get('/app/logs', RecordingController::class, 'logs');
        $routing->get('/app/logs/{id}', RecordingController::class, 'logEvent');

        return $routing;
    }
}

final class RecordingController extends AppController
{
    public static ?array $lastCall = null;

    public function logs(): void
    {
        self::$lastCall = ['logs'];
    }

    public function logEvent(int $id): void
    {
        self::$lastCall = ['logEvent', $id];
    }
}
