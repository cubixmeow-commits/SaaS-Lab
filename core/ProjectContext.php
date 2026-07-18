<?php

declare(strict_types=1);

final class ProjectContext
{
    private static ?Project $project = null;
    private static ?Database $database = null;

    public static function set(Project $project): void
    {
        self::$project = $project;
        self::$database = Database::project($project->databasePath());
    }

    public static function clear(): void
    {
        self::$project = null;
        self::$database = null;
    }

    public static function project(): Project
    {
        if (self::$project === null) {
            throw new RuntimeException('No project is loaded in the current request.');
        }

        return self::$project;
    }

    public static function database(): Database
    {
        if (self::$database === null) {
            throw new RuntimeException('No project database is loaded in the current request.');
        }

        return self::$database;
    }

    public static function has(): bool
    {
        return self::$project !== null;
    }
}
