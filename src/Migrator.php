<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Brings the database schema up to date with the files in database/migrations: each file once, in the order of
 * their names. bin/migrate.php runs it before the app, the bot and cron start.
 */
final class Migrator
{
    // a number first, so the files sort in the order they were written: 0002_add_feedback
    private const NAME_PATTERN = '/^(\d{4})_[a-z0-9_]+$/';
    // the schema databases were created from before there were migrations
    private const FIRST = '0001_initial_schema';
    // any number, the same in every run, so two runs at once wait for each other
    private const LOCK_KEY = 4_172_026;

    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    /**
     * Applies the migrations not applied yet. Each runs in its own transaction together with its record
     * in schema_migrations, so a failed one leaves the database as it was before it.
     *
     * @param callable(string): void $log
     * @return list<string> the names applied now
     */
    public function migrate(callable $log): array
    {
        $this->pdo->query('SELECT pg_advisory_lock(' . self::LOCK_KEY . ')');

        try {
            $this->prepareTable($log);
            $applied = $this->pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
            $pending = self::pending($this->files(), $applied);

            foreach ($pending as $name) {
                $this->apply($name);
                $log("Applied $name");
            }

            return $pending;
        } finally {
            $this->pdo->query('SELECT pg_advisory_unlock(' . self::LOCK_KEY . ')');
        }
    }

    /**
     * The files not applied yet, in order. A database with a migration that has no file here is newer than the code,
     * or a file was renamed, so it stops instead of guessing.
     *
     * @param list<string> $files the names without .sql
     * @param list<string> $applied
     * @return list<string>
     */
    public static function pending(array $files, array $applied): array
    {
        $numbers = [];

        foreach ($files as $name) {
            if (!preg_match(self::NAME_PATTERN, $name, $match)) {
                throw new RuntimeException("The migration $name.sql needs a name like 0002_add_something.sql");
            }

            if (isset($numbers[$match[1]])) {
                throw new RuntimeException("The migrations {$numbers[$match[1]]} and $name have the same number");
            }

            $numbers[$match[1]] = $name;
        }

        $unknown = array_diff($applied, $files);

        if ($unknown !== []) {
            throw new RuntimeException('The database has migrations with no file: ' . implode(', ', $unknown));
        }

        $pending = array_values(array_diff($files, $applied));
        sort($pending, SORT_STRING);

        return $pending;
    }

    /**
     * Creates schema_migrations the first time. A database made from the old schema.sql already has the first
     * migration's tables, so that migration is recorded for it without running.
     *
     * @param callable(string): void $log
     */
    private function prepareTable(callable $log): void
    {
        if ($this->hasTable('schema_migrations')) {
            return;
        }

        $isFromSchemaFile = $this->hasTable('servers');

        $this->pdo->beginTransaction();
        $this->pdo->exec(
            'CREATE TABLE schema_migrations (
                name TEXT PRIMARY KEY,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )',
        );

        if ($isFromSchemaFile) {
            $this->record(self::FIRST);
            $log('The database was made from schema.sql, so ' . self::FIRST . ' is recorded without running');
        }

        $this->pdo->commit();
    }

    private function apply(string $name): void
    {
        $sql = file_get_contents("{$this->directory}/$name.sql");

        if ($sql === false) {
            throw new RuntimeException("The migration $name.sql could not be read");
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec($sql);
            $this->record($name);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();

            throw new RuntimeException("The migration $name failed: " . $error->getMessage(), 0, $error);
        }
    }

    private function record(string $name): void
    {
        $this->pdo->prepare('INSERT INTO schema_migrations (name) VALUES (:name)')->execute(['name' => $name]);
    }

    private function hasTable(string $table): bool
    {
        $statement = $this->pdo->prepare('SELECT to_regclass(:table) IS NOT NULL');
        $statement->execute(['table' => "public.$table"]);

        return $statement->fetchColumn() === true;
    }

    /** @return list<string> the names of the .sql files, without .sql */
    private function files(): array
    {
        return array_map(fn(string $path): string => basename($path, '.sql'), glob("{$this->directory}/*.sql") ?: []);
    }
}
