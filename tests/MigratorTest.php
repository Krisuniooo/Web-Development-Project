<?php

declare(strict_types=1);

namespace Tests;

use App\Migrator;
use RuntimeException;

final class MigratorTest extends TestCase
{
    public function testPendingAreTheNotAppliedOnesInOrder(): void
    {
        $this->assertSame(
            ['0002_add_feedback', '0003_add_indexes'],
            Migrator::pending(['0003_add_indexes', '0001_initial_schema', '0002_add_feedback'], ['0001_initial_schema']),
        );
    }

    public function testNothingIsPendingWhenAllAreApplied(): void
    {
        $this->assertSame([], Migrator::pending(['0001_initial_schema'], ['0001_initial_schema']));
    }

    public function testRefusesAFileWithoutANumber(): void
    {
        $this->assertThrows(RuntimeException::class, fn() => Migrator::pending(['add_feedback'], []));
    }

    public function testRefusesTwoFilesWithTheSameNumber(): void
    {
        $this->assertThrows(
            RuntimeException::class,
            fn() => Migrator::pending(['0002_add_feedback', '0002_add_indexes'], []),
        );
    }

    public function testRefusesAnAppliedMigrationWithNoFile(): void
    {
        $this->assertThrows(
            RuntimeException::class,
            fn() => Migrator::pending(['0001_initial_schema'], ['0001_initial_schema', '0002_renamed']),
        );
    }
}
