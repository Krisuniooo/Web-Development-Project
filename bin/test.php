<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/tests/TestCase.php';

// Runs the unit tests: every public test... method of every class in tests/*Test.php, each on a new object.
// They need no database, no network and no model, so they can run anywhere and any number of times:
// docker compose exec -T php php bin/test.php
// docker compose exec -T php php bin/test.php Permissions    only the classes with this text in their name
// The exit code is 1 when a test fails or none ran, so CI can stop on it.

$filter = $argv[1] ?? '';
$passed = 0;
$failed = 0;

foreach (glob(dirname(__DIR__) . '/tests/*Test.php') as $file) {
    $name = basename($file, '.php');

    if ($filter !== '' && stripos($name, $filter) === false) {
        continue;
    }

    require_once $file;
    $class = 'Tests\\' . $name;
    $passedHere = 0;
    $failures = [];

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        try {
            (new $class())->$method();
            $passedHere++;
        } catch (Throwable $error) {
            // the line of the test that failed, not the line of the check inside TestCase or of the tested code
            $line = $error->getFile() === $file ? $error->getLine() : null;

            foreach ($error->getTrace() as $frame) {
                if ($line === null && ($frame['file'] ?? null) === $file) {
                    $line = $frame['line'];
                }
            }

            $failures[] = sprintf("  FAIL %s (tests/%s.php:%s)\n     %s", $method, $name, $line ?? '?', $error->getMessage());
        }
    }

    printf("%-28s %d passed, %d failed\n", $name, $passedHere, count($failures));

    if ($failures !== []) {
        echo implode("\n", $failures) . "\n";
    }

    $passed += $passedHere;
    $failed += count($failures);
}

printf("\n%d tests, %d passed, %d failed\n", $passed + $failed, $passed, $failed);
exit($failed === 0 && $passed > 0 ? 0 : 1);
